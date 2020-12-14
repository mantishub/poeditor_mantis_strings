<?php
if( $argc < 2 ) {
    echo "Missing path to MantisHub folder.\n";
    exit;
}

$t_projects = [
    'Helpdesk',
    'AuthHub',
    'ImportUsers',
    'LiveLinks',
    'EventLog',
    'MantisHub',
    'Slack',
    'TrimAttachments',
  ];
  
$t_path = rtrim( realpath( trim( $argv[1] ) ), '/' ) . '/';

foreach( $t_projects as $t_project ) {
    $t_english_path = $t_path . 'plugins/' . $t_project . '/lang/strings_english.txt';
    $t_folder = './output/' . $t_project . '/en/';
    mkdir( $t_folder, 0777, true );
    generate_csv_for_file( $t_english_path, $t_folder . 'english.csv' );
}

function generate_csv_for_file( $p_strings_file, $p_output_file ) {
    if( !file_exists( $p_strings_file ) ) {
        echo "File '$p_strings_file' not found.\n";
        exit;
    }

    echo "Processing '$p_strings_file'...\n";
    include( $p_strings_file );

    $t_vars = get_defined_vars();
    $t_strings = [];

    foreach( $t_vars as $t_name => $t_value ) {
        if( stripos( $t_name, 's_' ) !== 0 && $t_name != 'MANTIS_ERROR' ) {
            continue;
        }

        if( $t_name == 'MANTIS_ERROR' ) {
            foreach( $t_value as $t_error_name => $t_error_value ) {
                $t_strings['MANTIS_ERROR_' . $t_error_name] = $t_error_value;
            }
        } else {
            $t_string_name = substr( $t_name, 2 );
            $t_strings[$t_string_name] = $t_value;
        }
    }

    $t_fp = fopen( $p_output_file, 'w' );

    if( !empty( $t_strings ) ) {
        foreach ( $t_strings as $t_name => $t_value ) {
            fputcsv( $t_fp, [ $t_name, $t_value ] );
        }
    }

    fclose( $t_fp );
}
