<?php
# Export the terms of a project from poeditor.com into Mantis
# string files.
#
# Setup
# - Create `~/.poeditor_api_token` file with content including
#   the poeditor.com API key.
# - Create `.poeditor_project_id` with its contents being the
#   project id of the project to be imported.  This file should
#   be in the folder where this script will be run from to generate
#   the string files.

$language_map = [
    'en' => 'strings_english.txt',
    'zh-Hans' => 'strings_chinese_simplified.txt',
    'fr' => 'strings_french.txt',
    'de' => 'strings_german.txt',
    'pt-br' => ['strings_portuguese_brazil.txt', 'strings_portuguese_standard.txt' ],
    'es' => 'strings_spanish.txt',
];

$api_token_path = $_SERVER['HOME'] . '/.poeditor_api_token';
$api_token = trim( file_get_contents( $api_token_path ) );
$project_id = trim( file_get_contents( getcwd() . '/.poeditor_project_id' ) );
$target_language = 'english';

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

$languages = api( 'languages/list', [ 'id' => $project_id ] );
$lang_codes = ['en'];
foreach( $languages->languages as $language ) {
    $lang_codes[] = $language->code;
}

$english_strings = [];

foreach( $lang_codes as $lang_code ) {
    echo "Fetching $lang_code...\n";
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
      echo "  Generating $output_file...\n";
      $t_output = '';
      $t_output .= "<?php\n";
      $t_output .= '# Filename: ' . $output_file . "\n";
      $t_output .= '# POEditor Project ID: ' . $project_id . "\n";
      $t_output .= '# POEditor Language: ' . $lang_code . "\n";
      $t_output .= '# Exported on: ' . date( 'c') . "\n\n";
      foreach( $json->terms as $term ) {
          $value = $term->translation->content;
          if( $lang_code == 'en' ) {
            $english_strings[$term->term] = $term->translation->content;
          } else if( empty( $value ) ) {
            $value = $english_strings[$term->term];
          }

          $t_output .= '$s_' . $term->term . ' = "' . $value . '";' . "\n";
      }
  
      $t_output .= "\n";
      file_put_contents( $output_file, $t_output );  
    }
}

echo "\n";
