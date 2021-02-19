<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require __DIR__.'/../vendor/autoload.php';

class Zoom
{
  protected $CI;
  protected $CLIENT_ID;
  protected $CLIENT_SECRET;
  protected $REDIRECT_URI;
  protected $CLIENT;
  protected $CREDENTIAL_PATH;
  protected $CREDENTIAL_DATA;

  public function __construct()
  {
    // Assign the CodeIgniter super-object
    $this->CI =& get_instance();
    $this->CI->load->helper('url');


    $this->CLIENT_ID = 'Your-client-id';
    $this->CLIENT_SECRET = 'Your-client-secret';
    $this->REDIRECT_URI = base_url().'zoomController/callback';
    $this->CLIENT = new GuzzleHttp\Client(['base_uri' => 'https://api.zoom.us']);
    $this->CREDENTIAL_PATH = __DIR__.'/zoom-oauth-credentials.json';
    $this->CREDENTIAL_DATA = json_decode(file_get_contents($this->CREDENTIAL_PATH), true);
  }

  public function index()
  {
    $url = "https://zoom.us/oauth/authorize?response_type=code&client_id=".$this->CLIENT_ID."&redirect_uri=".$this->REDIRECT_URI;

    echo "<a href=".$url.">Login with Zoom</a>";
  }

  public function createToken($code)
  {
    $response = $this->CLIENT->request('POST', '/oauth/token', [
        "headers" => [
            "Authorization" => "Basic ". base64_encode($this->CLIENT_ID.':'.$this->CLIENT_SECRET)
        ],
        'form_params' => [
            "grant_type" => "authorization_code",
            "code" => $code,
            "redirect_uri" => $this->REDIRECT_URI
        ],
    ]);

    $token = json_encode(json_decode($response->getBody()->getContents(), true));
    file_put_contents($this->CREDENTIAL_PATH, $token);
    if (!file_exists($this->CREDENTIAL_PATH)) {
      echo 'Error while saving file';
      return;
    }
    echo 'Token saved successfully';
  }

  public function refreshToken()
  {
    try {
      $response = $this->CLIENT->request('POST', '/oauth/token', [
          "headers" => [
              "Authorization" => "Basic ".base64_encode($this->CLIENT_ID.':'.$this->CLIENT_SECRET)
          ],
          'form_params' => [
              "grant_type" => "refresh_token",
              "refresh_token" => $this->CREDENTIAL_DATA['refresh_token']
          ],
      ]);
      $token = json_encode(json_decode($response->getBody()->getContents(), true));
      file_put_contents($this->CREDENTIAL_PATH, $token);
      $this->CREDENTIAL_DATA = json_decode(file_get_contents($this->CREDENTIAL_PATH), true);
      return true;
    } catch (\Exception $e) {
      echo 'Failed during refresh token '.$e->getMessage();
      return false;
    }
  }

// https://marketplace.zoom.us/docs/api-reference/zoom-api/meetings/meetings
  public function listMeeting($query=[])
  {
    try {
      $response = $this->CLIENT->request('GET', '/v2/users/me/meetings', [
          "headers" => [
              "Authorization" => "Bearer ".$this->CREDENTIAL_DATA['access_token']
          ],
          'query' => $query
      ]);

      return array('status' => true, 'data' => json_decode($response->getBody(), true));
    } catch (\Exception $e) {
      if( $e->getCode() == 401 && $this->refreshToken()) {
        $this->listMeeting($query);
      } else {
        return array('status' => false, 'message' => $e->getMessage());
      }
    }
  }

// https://marketplace.zoom.us/docs/api-reference/zoom-api/meetings/meetingcreate
  public function createMeeting($eventCreationData)
  {
    try {
      $response = $this->CLIENT->request('POST', '/v2/users/me/meetings', [
          "headers" => [
              "Authorization" => "Bearer ".$this->CREDENTIAL_DATA['access_token']
          ],
          'json' => $eventCreationData
      ]);

      if ($response->getStatusCode() == 201) {
        return array('status' => true, 'data' => json_decode($response->getBody(), true));
      }

      throw new Exception("Not able to find error");
    } catch (\Exception $e) {
      if( $e->getCode() == 401 && $this->refreshToken()) {
        return $this->createMeeting($eventCreationData);
      }
      if ($e->getCode() == 300) {
        return array('status' => false, 'message' => 'Invalid enforce_login_domains, separate multiple domains by semicolon. A maximum of {rateLimitNumber} meetings can be created/updated for a single user in one day.');
      }
      if ($e->getCode() == 404) {
        return array('status' => false, 'message' => 'User {userId} not exist or not belong to this account.');
      }
      if( $e->getCode() != 401 ) {
        return array('status' => false, 'message' => $e->getMessage());
      }
      return array('status' => false, 'message' => 'Not able to refresh token');
    }
  }

  public function deleteMeeting($meeting_id, $deleteMeetingData)
  {
    try {
      $response = $this->CLIENT->request('DELETE', '/v2/meetings/'.$meeting_id, [
          "headers" => [
              "Authorization" => "Bearer ".$this->CREDENTIAL_DATA['access_token']
          ],
          'query' => $deleteMeetingData
      ]);

      if ($response->getStatusCode() == 204) {
        return array('status' => true, 'message' => 'Meeting deleted.');
      }
      throw new Exception("Not able to find error");
    } catch (\Exception $e) {
      if( $e->getCode() == 401 && $this->refreshToken()) {
        return $this->deleteMeeting($meeting_id, $deleteMeetingData);
      }
      if ($e->getCode() == 400) {
        return array('status' => false, 'message' => 'User does not belong to this account or dont have access');
      }
      if ($e->getCode() == 404) {
        return array('status' => false, 'message' => 'Meeting with this {meetingId} is not found or has expired.');
      }
      if( $e->getCode() != 401 ) {
        return array('status' => false, 'message' => $e->getMessage());
      }
      return array('status' => false, 'message' => 'Not able to refresh token');
    }
  }


  public function addMeetingRegistrant($meeting_id, $json)
  {
    try {
      $response = $this->CLIENT->request('POST', '/v2/meetings/'.$meeting_id.'/registrants', [
          "headers" => [
              "Authorization" => "Bearer ".$this->CREDENTIAL_DATA['access_token']
          ],
          'json' => $json
      ]);

      if ($response->getStatusCode() == 201) {
        return array('status' => true, 'message' => 'Registration successfull', 'data' => json_decode($response->getBody(), true) );
      }

      throw new Exception("Not able to find error");
    }
    catch (\Exception $e) {
      if( $e->getCode() == 401 && $this->refreshToken() ) {
        return $this->addMeetingRegistrant($meeting_id, $json);
      }
      if ($e->getCode() == 300) {
        return array('status' => false, 'message' => 'Meeting {meetingId} is not found or has expired.');
      }
      if ($e->getCode() == 400) {
        return array('status' => false, 'message' => 'Access error. Not have correct access. validation failed');
      }
      if ($e->getCode() == 404) {
        return array('status' => false, 'message' => 'Meeting not found or Meeting host does not exist: {userId}.');
      }
      if( $e->getCode() != 401 ) {
        return array('status' => false, 'message' => $e->getMessage());
      }
      return array('status' => false, 'message' => 'Not able to refresh token');
    }
  }

}
