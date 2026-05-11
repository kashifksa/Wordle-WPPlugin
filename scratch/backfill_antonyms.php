<?php
/**
 * Backfill missing antonyms using Groq AI.
 */
require_once(__DIR__ . '/../../../../wp-load.php');
require_once(WORDLE_HINT_PATH . 'includes/class-wordle-ai.php');

global $wpdb;
$table = $wpdb->prefix . 'wordle_data';

// Find words with empty antonyms
$words = $wpdb->get_results("SELECT id, word, synonyms, antonyms, definition, etymology, example_sentence FROM $table WHERE (antonyms = '[]' OR antonyms IS NULL OR antonyms = '') AND num_definitions > 0 LIMIT 20");

if (empty($words)) {
    echo "No words found needing antonym enrichment.\n";
    exit;
}

echo "Starting backfill for " . count($words) . " words...\n";

foreach ($words as $row) {
    echo "Processing '{$row->word}' (ID: {$row->id})... ";
    
    $current_data = array(
        'synonyms' => $row->synonyms,
        'antonyms' => $row->antonyms,
        'definition' => $row->definition,
        'etymology' => $row->etymology,
        'example_sentence' => $row->example_sentence
    );

    $enriched = Wordle_AI::enrich_dictionary_data($row->word, $current_data);

    if ($enriched['antonyms'] !== $row->antonyms && $enriched['antonyms'] !== '[]') {
        $wpdb->update(
            $table,
            array(
                'antonyms' => $enriched['antonyms'],
                'synonyms' => $enriched['synonyms'],
                'definition' => $enriched['definition'],
                'etymology' => $enriched['etymology'],
                'example_sentence' => $enriched['example_sentence']
            ),
            array('id' => $row->id)
        );
        echo "Done (Antonyms: " . $enriched['antonyms'] . ")\n";
    } else {
        echo "No changes found or AI returned empty.\n";
    }
    
    // Small delay to respect rate limits
    usleep(500000); // 0.5s
}

echo "\nBatch complete. Run again to process more.\n";
