<?php
require_once('../../../wp-load.php');
$puzzle = Wordle_DB::get_puzzle_by_date('2026-05-12');
echo "Puzzle for 2026-05-12:\n";
print_r($puzzle);
