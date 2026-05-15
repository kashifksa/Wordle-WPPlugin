<?php
$csv_file = 'c:/xampp/htdocs/TodayWordle/wp-content/plugins/Wordle-WPPlugin/wordle_dataset_enriched.csv';
if ( file_exists( $csv_file ) ) {
    if ( ( $handle = fopen( $csv_file, "r" ) ) !== FALSE ) {
        $header = fgetcsv( $handle ); 
        while ( ( $data = fgetcsv( $handle ) ) !== FALSE ) {
            if ( isset( $data[2] ) ) {
                $word = strtoupper( trim( $data[2] ) );
                if ($word === 'SAUCY') {
                    echo "Found SAUCY! Length: " . strlen($word) . "\n";
                    var_dump($data);
                }
            }
        }
        fclose( $handle );
    }
} else {
    echo "File not found\n";
}
