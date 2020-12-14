<?php
# Export the terms of one or more projects from poeditor.com into Mantis
# string files.
#
# Setup
# - Create `~/.poeditor_api_token` file with content including
#   the poeditor.com API key.

$language_map = [
    'en' => 'strings_english.txt',
    'zh-Hans' => 'strings_chinese_simplified.txt',
    'fr' => 'strings_french.txt',
    'de' => 'strings_german.txt',
    'pt-br' => ['strings_portuguese_brazil.txt', 'strings_portuguese_standard.txt' ],
    'es' => 'strings_spanish.txt',
    'ru' => 'strings_russian.txt',
];

$project_map = [
  260729 => 'AuthHub',
  260967 => 'EventLog',
  260961 => 'Helpdesk',
  397505 => 'Kanban',
  260963 => 'ImportUsers',
  260965 => 'LiveLinks',
  260969 => 'MantisHub',
  260971 => 'Slack',
  397509 => 'Teams',
  260973 => 'TrimAttachments',
];

$api_token_path = $_SERVER['HOME'] . '/.poeditor_api_token';
$api_token = trim( file_get_contents( $api_token_path ) );
$target_language = 'english';

function get_project_id( $project ) {
  if( is_numeric( $project ) ) {
    return $project;
  }

  global $project_map;

  foreach( $project_map as $t_id => $t_name ) {
    if( $project == $t_name ) {
      return $t_id;
    }
  }

  echo "Project '$project' not found.\n";
  exit;
}

function api( $api, $params ) {
  global $api_token;

  $post_fields = 'api_token=' . $api_token;
  foreach( $params as $key => $value  ) {
    $post_fields .= '&' . $key . '=' . $value;
  }

  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.poeditor.com/v2/" . $api,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => $post_fields,
    CURLOPT_HTTPHEADER => [
      "cache-control: no-cache",
      "content-type: application/x-www-form-urlencoded",
    ],
  ]);

  $response = curl_exec($curl);
  $err = curl_error($curl);
  
  curl_close($curl);
  
  if ($err) {
    echo "cURL Error #:" . $err;
    exit;
  }

  $json = json_decode( $response );
  if( $json->response->code != '200' ) {
    echo 'ERROR: ' . $json->response->message . "\n";
    exit;
  }

  return $json->result;
}

function project_download( $project_id ) {
  global $language_map, $project_map;

  $t_project_name = $project_map[$project_id];

  echo "Fetching strings for project `$t_project_name`\n";

  $languages = api( 'languages/list', [ 'id' => $project_id ] );
  $lang_codes = ['en'];
  foreach( $languages->languages as $language ) {
      $lang_codes[] = $language->code;
  }

  $english_strings = [];

  foreach( $lang_codes as $lang_code ) {
      $t_folder = 'output/' . $t_project_name . '/';

      if( !file_exists( $t_folder ) ) {
        mkdir( $t_folder, 0777, true );
      }

      # Don't generate english files.
      if( $lang_code == 'en' ) {
        $t_folder .= 'en/';
      }

      if( !file_exists( $t_folder ) ) {
        mkdir( $t_folder, 0777, true );
      }

      echo "    $lang_code\n";
      $json = api( 'terms/list', [ 'id' => $project_id, 'language' => $lang_code ] );
  
      if( !isset( $language_map[$lang_code] ) ) {
        echo "Unknown language '$lang_code'.\n";
        continue;
      }
  
      $output_file_names = $language_map[$lang_code];
      if( !is_array( $output_file_names ) ) {
        $output_file_names = array( $output_file_names );
      }
  
      foreach( $output_file_names as $output_file ) {
        echo "        $output_file\n";
        $t_output = '';
        $t_output .= "<?php\n";
        $t_output .= '# Filename: ' . $output_file . "\n";
        $t_output .= '# POEditor Project ID: ' . $project_id . "\n";
        $t_output .= '# POEditor Language: ' . $lang_code . "\n";
        $t_output .= '# Exported on: ' . date( 'c') . "\n\n";
        foreach( $json->terms as $term ) {
            $value = $term->translation->content;
            if( $lang_code == 'en' ) {
              $value = $term->translation->content;
              $english_strings[$term->term] = $value;
            } else if( empty( $value ) ) {
              $value = $english_strings[$term->term];
            }

            $value = str_replace( '"', '\"', $value );
  
            if( stripos( $term->term, 'MANTIS_ERROR_' ) === 0 ) {
              $t_output .= "\$MANTIS_ERROR['" . substr( $term->term, 13 ) . "'] = " . '"' . $value . '";' . "\n";
            } else {
              $t_output .= '$s_' . $term->term . ' = "' . $value . '";' . "\n";
            }
        }
    
        $t_output .= "\n";

        $t_file_path = $t_folder . $output_file;
        file_put_contents( $t_file_path, $t_output );

        $t_cmd = "php -l $t_file_path";
        $t_result = shell_exec( $t_cmd ) . "\n";

        if( strpos( $t_result, 'No syntax errors detected' ) !== false ) {
            echo "        no syntax errors.\n";
        } else {
            echo "        $t_result\n";
        }
      }
  }  
}

if( $argc >= 2 ) {
  $project = $argv[1];
  $t_project_id = get_project_id( $project );
  project_download( $t_project_id );
} else {
  foreach( $project_map as $t_project_id => $t_project_name ) {
    project_download( $t_project_id );
  }
}

echo "\n";
