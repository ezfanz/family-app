<?php

namespace Tests\Feature\Api;

use App\Enums\TripRole;
use App\Models\ShoppingItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class ShoppingImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['No', 'Store', 'Item name', 'Details (colour, size, code)', 'Qty', 'Est. price each (¥)', 'Est. total (¥)', 'Est. total (RM)', 'For (who)', 'Buyer', 'Link', 'Notes', 'Bought (Y/N)', 'Paid (¥)'];

    private function template(): UploadedFile
    {
        return new UploadedFile(public_path('templates/e-itinerary-shopping-list.xlsx'), 'shopping-list.xlsx', null, null, true);
    }

    /**
     * Build a small .xlsx the way Excel saves it (shared strings).
     *
     * @param  list<list<string|int|float|null>>  $rows  data rows under the template header
     */
    private function xlsx(array $rows, string $name = 'list.xlsx'): UploadedFile
    {
        $strings = [];
        $index = function (string $text) use (&$strings): int {
            $key = array_search($text, $strings, true);
            if ($key === false) {
                $strings[] = $text;
                $key = count($strings) - 1;
            }

            return $key;
        };

        $xmlRows = '';
        foreach (array_merge([self::HEADERS], $rows) as $r => $cells) {
            $xmlRows .= '<row r="'.($r + 1).'">';
            foreach (array_values($cells) as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $ref = chr(65 + $c).($r + 1);
                $xmlRows .= is_string($value)
                    ? '<c r="'.$ref.'" t="s"><v>'.$index($value).'</v></c>'
                    : '<c r="'.$ref.'"><v>'.$value.'</v></c>';
            }
            $xmlRows .= '</row>';
        }

        $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $sst = '<sst '.$ns.'>'.implode('', array_map(fn ($s) => '<si><t>'.htmlspecialchars($s, ENT_XML1).'</t></si>', $strings)).'</sst>';

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook '.$ns.' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Shopping list" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet '.$ns.'><sheetData>'.$xmlRows.'</sheetData></worksheet>');
        $zip->addFromString('xl/sharedStrings.xml', $sst);
        $zip->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_checking_the_template_plans_items_without_saving(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();

        $response = $this->actingAs($viewer)->post("/api/trips/{$trip->id}/shopping-import", ['file' => $this->template()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('applied', false)
            ->assertJsonPath('summary.error', 0)
            ->assertJsonPath('rows.0.name', 'Water-Repellent Recycled Nylon Packable Boston Bag 23L')
            ->assertJsonPath('rows.0.action', 'create');

        $this->assertGreaterThanOrEqual(13, $response->json('summary.create'));
        $this->assertSame(0, ShoppingItem::where('user_id', $viewer->id)->count());
    }

    public function test_confirming_imports_into_my_list_and_reimporting_does_not_duplicate(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();

        $created = $this->actingAs($viewer)->post("/api/trips/{$trip->id}/shopping-import", ['file' => $this->template(), 'confirm' => 1], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('applied', true)->json('summary.create');

        $item = ShoppingItem::where('user_id', $viewer->id)->where('name', 'Lip Essence Lip Balm')->sole();
        $this->assertSame('MUJI', $item->store);
        $this->assertSame('Pink, code 02', $item->details);
        $this->assertStringStartsWith('https://', $item->link);
        $this->assertSame($created, ShoppingItem::where('user_id', $viewer->id)->count());

        $this->actingAs($viewer)->post("/api/trips/{$trip->id}/shopping-import", ['file' => $this->template(), 'confirm' => 1], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('summary.create', 0)->assertJsonPath('summary.unchanged', $created);
        $this->assertSame($created, ShoppingItem::where('user_id', $viewer->id)->count());
    }

    public function test_rows_update_existing_items_and_empty_cells_keep_app_values(): void
    {
        ['trip' => $trip, 'editor' => $editor] = $this->tripFixture();
        $item = ShoppingItem::factory()->for($trip)->create(['user_id' => $editor->id, 'name' => 'Tokyo Banana', 'details' => '8 pcs', 'qty' => 1, 'estimated_yen' => 1200, 'actual_yen' => 1150, 'is_bought' => true]);

        $this->actingAs($editor)->post("/api/trips/{$trip->id}/shopping-import", ['file' => $this->xlsx([
            [1, 'Don Quijote', 'Tokyo Banana', '8 pcs', 2, 1200, null, null, 'For the office', null, null, null, null, null],
        ]), 'confirm' => 1], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('summary.update', 1);

        $item->refresh();
        $this->assertSame(2, $item->qty);
        $this->assertSame(2400, $item->estimated_yen);
        $this->assertSame('For the office', $item->for_whom);
        $this->assertSame(1150, $item->actual_yen);
        $this->assertTrue($item->is_bought);
    }

    public function test_owner_can_import_for_others_with_the_buyer_column(): void
    {
        ['trip' => $trip, 'owner' => $owner] = $this->tripFixture();
        $amira = User::factory()->create(['name' => 'Amira Binti Ali']);
        $trip->addMember($amira, TripRole::Editor);

        $this->actingAs($owner)->post("/api/trips/{$trip->id}/shopping-import", ['file' => $this->xlsx([
            [1, 'Daiso', 'Storage boxes', null, 10, 110, null, null, 'For the house', 'Amira', null, null, 'Y', 1100],
        ]), 'confirm' => 1], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('summary.create', 1)->assertJsonPath('rows.0.buyer', 'Amira Binti Ali');

        $item = ShoppingItem::where('name', 'Storage boxes')->sole();
        $this->assertSame($amira->id, $item->user_id);
        $this->assertSame(1100, $item->estimated_yen);
        $this->assertSame(1100, $item->actual_yen);
        $this->assertTrue($item->is_bought);
    }

    public function test_bad_rows_are_reported_and_skipped(): void
    {
        ['trip' => $trip, 'viewer' => $viewer, 'editor' => $editor] = $this->tripFixture();

        $response = $this->actingAs($viewer)->post("/api/trips/{$trip->id}/shopping-import", ['file' => $this->xlsx([
            [1, 'MUJI', 'Good item', null, 1, 500, null, null, null, null, null, null, null, null],
            [2, 'MUJI', null, 'no name here', 1, null, null, null, null, null, null, null, null, null],
            [3, 'MUJI', 'Bad qty', null, 'two', null, null, null, null, null, null, null, null, null],
            [4, 'MUJI', 'Bad bought', null, 1, null, null, null, null, null, null, null, 'maybe', null],
            [5, 'MUJI', 'Bad link', null, 1, null, null, null, null, null, 'not a url', null, null, null],
            [6, 'MUJI', 'For someone else', null, 1, null, null, null, null, $editor->name, null, null, null, null],
            [7, 'MUJI', 'Good item', null, 1, null, null, null, null, null, null, null, null, null],
        ]), 'confirm' => 1], ['Accept' => 'application/json'])->assertOk();

        $response->assertJsonPath('summary.create', 1)->assertJsonPath('summary.error', 6);
        $messages = collect($response->json('rows'))->pluck('message')->filter()->implode(' | ');
        $this->assertStringContainsString('Item name is empty', $messages);
        $this->assertStringContainsString('not a number', $messages);
        $this->assertStringContainsString('Bought must be Y or N', $messages);
        $this->assertStringContainsString('web address', $messages);
        $this->assertStringContainsString('Only the trip owner', $messages);
        $this->assertStringContainsString('Same item as row 2', $messages);
        $this->assertSame(1, ShoppingItem::where('user_id', $viewer->id)->count());
    }

    public function test_only_real_xlsx_files_are_accepted(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();

        $this->actingAs($viewer)->post("/api/trips/{$trip->id}/shopping-import", ['file' => UploadedFile::fake()->createWithContent('list.csv', "Item name\nBanana")], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->actingAs($viewer)->post("/api/trips/{$trip->id}/shopping-import", ['file' => UploadedFile::fake()->createWithContent('fake.xlsx', 'not a zip file')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.file.0', 'This file is not a valid Excel (.xlsx) file.');
    }

    public function test_outsiders_cannot_import(): void
    {
        ['trip' => $trip, 'outsider' => $outsider] = $this->tripFixture();

        $this->actingAs($outsider)->post("/api/trips/{$trip->id}/shopping-import", ['file' => $this->template()], ['Accept' => 'application/json'])
            ->assertNotFound();
    }
}
