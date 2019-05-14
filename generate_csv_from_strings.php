<?php
if( $argc < 2 ) {
    $strings_file = dirname( $argc[0] ) . '/strings_english.txt';
} else {
    $strings_file = $argv[1];
}

if( !file_exists( $strings_file ) ) {
    echo "File '$strings_file' not found.\n";
    exit;
}

echo "Processing '$strings_file'...\n";
include( realpath( $strings_file ) );

$vars = get_defined_vars();
$strings = [];

foreach( $vars as $name => $value ) {
    if( stripos( $name, 's_' ) !== 0 && $name != 'MANTIS_ERROR' ) {
        continue;
    }

    if( $name == 'MANTIS_ERROR' ) {
        foreach( $value as $error_name => $error_value ) {
            $strings['MANTIS_ERROR_' . $error_name] = $error_value;
        }
    } else {
        $string_name = substr( $name, 2 );
        $strings[$string_name] = $value;
    }
}

$output_file_path = str_replace( '.txt', '.csv', end( explode( '/', $strings_file ) ) );
if( file_exists( $output_file_path ) ) {
    unlink( $output_file_path );
}

if( !empty( $strings ) ) {
    $fp = fopen( $output_file_path, 'w' );

    foreach ( $strings as $name => $value ) {
        fputcsv( $fp, [ $name, $value ] );
    }

    fclose($fp);
}

