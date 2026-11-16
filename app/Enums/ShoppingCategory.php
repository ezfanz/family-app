<?php

namespace App\Enums;

/**
 * Fixed shopping categories for the Shopping overview.
 */
enum ShoppingCategory: string
{
    case ClothingAndBags = 'clothing_bags';
    case HomeAndKitchen = 'home_kitchen';
    case SnacksAndFood = 'snacks_food';
    case GamesAndToys = 'games_toys';
    case Beauty = 'beauty';
    case Other = 'other';

    /**
     * Label shown in the app.
     */
    public function label(): string
    {
        return match ($this) {
            self::ClothingAndBags => 'Clothing and bags',
            self::HomeAndKitchen => 'Home and kitchen',
            self::SnacksAndFood => 'Snacks and food',
            self::GamesAndToys => 'Games and toys',
            self::Beauty => 'Beauty',
            self::Other => 'Other',
        };
    }

    /**
     * Match a label or value from a spreadsheet ("Beauty", "snacks and food"), else Other.
     */
    public static function fromText(?string $text): self
    {
        $wanted = mb_strtolower(trim((string) $text));
        foreach (self::cases() as $case) {
            if ($wanted === $case->value || $wanted === mb_strtolower($case->label())) {
                return $case;
            }
        }

        return self::Other;
    }
}
