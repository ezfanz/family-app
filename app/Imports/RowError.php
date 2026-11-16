<?php

namespace App\Imports;

use RuntimeException;

/**
 * A problem with one spreadsheet row; the rest of the file can still import.
 */
class RowError extends RuntimeException {}
