<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ZoomController extends CI_Controller {
  function __construct() {
    parent::__construct();
    $this->load->library('zoom');
  }
	public function index()
	{
		$this->zoom->index();
	}
  public function callback()
  {
    $this->zoom->createToken($this->input->get('code'));
  }
  public function listMeeting()
	{
		$listMeetingData = $this->zoom->listMeeting(array());
    if ($listMeetingData['status']) {
      print_r($listMeetingData['data']);
      return;
    }
    echo $listMeetingData['message'];
	}
}
