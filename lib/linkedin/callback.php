<?php
  require('../../../../../wp-blog-header.php');
  require_once __DIR__ . '/vendor/autoload.php';
  use GuzzleHttp\Client;
  
  $redirect = admin_url( 'admin.php?page=arc-social-share-linkedin-settings' );
  try {
    $client = new Client(['base_uri' => 'https://www.linkedin.com']);
    $response = $client->request('POST', '/oauth/v2/accessToken', [
      'form_params' => [
        "grant_type"    => "authorization_code",
        "code"          => $_REQUEST['code'],
        "redirect_uri"  => get_option( 'arc_linkedin_redirect_uri' ),
        "client_id"     => get_option( 'arc_linkedin_client_id' ),
        "client_secret" => get_option( 'arc_linkedin_client_secret' )
      ],
    ]);
    $data = json_decode($response->getBody()->getContents(), true);
    if (FALSE === update_option('arc_linkedin_access_token', $data['access_token'])) 
      add_option( 'arc_linkedin_access_token', $data['access_token'] );

    if (FALSE === update_option('arc_linkedin_access_token_expires', $data['expires_in'])) 
      add_option( 'arc_linkedin_access_token_expires', $data['expires_in'] );
    
    $redirect = add_query_arg( 'status', 'success', $redirect );
  } catch(Exception $e) {
    $redirect = add_query_arg( 'status', 'error', $redirect );
    $redirect = add_query_arg( 'message', $e->getMessage(), $redirect );
  }

  wp_redirect( $redirect );
  exit;