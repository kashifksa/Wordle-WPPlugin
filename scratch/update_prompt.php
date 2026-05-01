<?php
define('WP_USE_THEMES', false);
// Dynamically find wp-load.php
$path = dirname(__FILE__);
while ($path !== dirname($path)) {
    if (file_exists($path . '/wp-load.php')) {
        require_once($path . '/wp-load.php');
        break;
    }
    $path = dirname($path);
}

$old_default = 'Generate 4 Wordle hints for the word {{WORD}}. Hint 1: vague, Hint 2: category, Hint 3: specific, Hint 4: final strong hint.';
$new_default = "Generate 4 progressive Wordle hints for the word {{WORD}}.\n\nRules:\n- FORBIDDEN: Do NOT use the word '{{WORD}}' or its plural.\n- FORBIDDEN: Do NOT use direct synonyms.\n- FORBIDDEN: Do NOT mention it has 5 letters (this is implied).\n- FORBIDDEN: Do NOT use rhymes.\n\nHint 1: Cryptic/Vague\nHint 2: Category/Context\nHint 3: Definition-style clue\nHint 4: Strong final hint";

$current = get_option('wordle_hint_ai_prompt');

if ($current === $old_default || empty($current)) {
    update_option('wordle_hint_ai_prompt', $new_default);
    echo "Prompt updated to new version.";
} else {
    echo "Prompt already customized or different. Current: " . $current;
}
