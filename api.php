<?php
require_once('config.php');
require_once('functions.php');
require_once('class/Session.class.php');
require_once('class/Utils.class.php');
require_once('class/Database.class.php');
require_once("class.session_handler.php");
require_once("classes/Project.class.php");
require_once("classes/User.class.php");
if (!defined("ALL_ASSETS"))      define("ALL_ASSETS", "all_assets");

// TODO: add API keys to these function calls
// uploadProfilePicture
// getSystemDrawerJobs
// getTimezone

if(validateAction()) {
    if(!empty($_REQUEST['action'])){
        mysql_connect (DB_SERVER, DB_USER, DB_PASSWORD);
        mysql_select_db (DB_NAME);
        switch($_REQUEST['action']){
            case 'updateuser':
                validateAPIKey();
                updateuser();
                break;
            case 'pushVerifyUser':
                validateAPIKey();
                pushVerifyUser();
                break;
            case 'login':
                validateAPIKey();
                loginUserIntoSession();
                break;
            case 'getLatestPosts':
                getLatestPosts();
                break;
            case 'getTaskPosts':
                getTaskPosts();
                break;
            case 'getLatestForNickname':
	            getLatestForNickname();
	            break;
            case 'uploadProfilePicture':
                uploadProfilePicture();
                break;
            case 'updateProjectList':
                validateAPIKey();
                updateProjectList();
                break;
            case 'getSystemDrawerJobs':
                getSystemDrawerJobs();
                break;
            case 'bidNotification':
                validateAPIKey();
                sendBidNotification();
                break;
            case 'processW2Masspay':
                validateAPIKey();
                processW2Masspay();
                break;
            case 'doScanAssets':
                validateAPIKey();
                doScanAssets();
                break;
            case 'version':
                validateAPIKey();
                exec('svnversion > ver');
                break;
            case 'jobsPastDue':
                validateAPIKey();
                sendPastDueNotification();
                break;
            case 'sendContactEmail':
                // @TODO: why do we require an API key for this?
                // I don't get it. The request is sent via JS, so if we included the API key it would
                // then become visible to all who want to see it, leaving the form open for abuse... - lithium
                // validateAPIKey();
                sendContactEmail();
                break;
            case 'getTimezone':
                getTimezone();
                break;
            case 'updateLastSeen':
                updateLastSeen();
                break;
            case 'sendTestNotifications':
                validateAPIKey();
                sendTestNotifications();
                break;
            case 'autoPass':
                validateAPIKey();
                autoPassSuggestedJobs();
                break;
            default:
                die("Invalid action.");
        }
    }
}

function validateAction() {
    if (validateRequest()) {
        return true;
    } else {
        return false;
    }
}

function validateRequest() {
    if( ! isset($_SERVER['HTTPS'])) {
        error_log("Only HTTPS connection is accepted.");
        die("Only HTTPS connection is accepted.");
    } else if ( ! isset($_REQUEST['action'])) {
        error_log("API not defined");
        die("API not defined");
    } else {
        return true;
    }
}

function validateAPIKey() {
    if( ! isset($_REQUEST["api_key"])) {
        error_log("No api key defined.");
        die("No api key defined.");
    //If we get the JOURNAL_API_KEY, only do journal queries
    } else if( (strcmp($_REQUEST["api_key"],JOURNAL_API_KEY) == 0 )
           &&  (!in_array($_REQUEST['action'],array('getLatestPosts','getTaskPosts'))) ) {
                error_log("Wrong api key provided.");
                die("Wrong api key provided.");
    } else if(strcmp($_REQUEST["api_key"],API_KEY) != 0 ) {
        error_log("Wrong api key provided.");
        die("Wrong api key provided.");
    } else {
        return true;
    }
}

function  getLatestPosts() {

	require_once('chat.class.php');
	$toTime = 0;
	$prevNext = '';
	$query = isset($_REQUEST['query']) ? $_REQUEST['query'] : '';
	require_once('class/AjaxResponse.class.php');
	$response = new AjaxResponse($chat);
	try
	{
		$data = $response->latest();
	}
	catch(Exception $e)
	{
		$data['error'] = $e->getMessage();
	}
	
	$json = json_encode($data);
	echo $json;
}

function  getTaskPosts() {

	require_once('chat.class.php');
	$toTime = "UNIX_TIMESTAMP()";
	$prevNext = 'prev';
	$query = isset($_REQUEST['query']) ? $_REQUEST['query'] : '';
	require_once('class/AjaxResponse.class.php');
	$response = new AjaxResponse($chat);
	try
	{
		$data = $response->latestFromTask($toTime, $prevNext);
	}
	catch(Exception $e)
	{
		$data['error'] = $e->getMessage();
	}
	
	$json = json_encode($data);
	echo $json;
}

// Created for Worklist Job #13424 [danbrown]
function getLatestForNickname() {

	// If we haven't specified the nickname, break out.
	if (!isset($_REQUEST['nickname'])) return false;

	// If we haven't specified the number of posts to return, or if it exceeds 100 or is less than one, default to 20.
	if (!isset($_REQUEST['num']) || !is_numeric($_REQUEST['num']) || $_REQUEST['num'] < 1 || $_REQUEST['num'] > 100) {
		$_REQUEST['num'] = 20;
	}

	require_once('chat.class.php');
	require_once('class/AjaxResponse.class.php');

	$response = new AjaxResponse($chat);

    try {
                $data = $response->latestForNickname($_REQUEST['nickname'],round($_REQUEST['num']));
	} catch (Exception $e) {
		$data['error'] = $e->getMessage();
	}

	echo $data['html'];
}

/*
* Setting session variables for the user so he is logged in
*/
function loginUserIntoSession(){
    require_once("class/Database.class.php");
    $db = new Database();
    $uid = (int) $_REQUEST['user_id'];
    $sid = $_REQUEST['session_id'];
    $csrf_token = md5(uniqid(rand(), TRUE));

    $sql = "SELECT * FROM ".WS_SESSIONS." WHERE session_id = '".mysql_real_escape_string($sid, $db->getLink())."'";
    $res = $db->query($sql);

    $session_data  ="running|s:4:\"true\";";
    $session_data .="userid|s:".strlen($uid).":\"".$uid."\";";
    $session_data .="username|s:".strlen($_REQUEST['username']).":\"".$_REQUEST['username']."\";";
    $session_data .="nickname|s:".strlen($_REQUEST['nickname']).":\"".$_REQUEST['nickname']."\";";
    $session_data .="admin|s:".strlen($_REQUEST['admin']).":\"".$_REQUEST['admin']."\";";
    $session_data .="csrf_token|s:".strlen($csrf_token).":\"".$csrf_token."\";";

    if(mysql_num_rows($res) > 0){
        $sql = "UPDATE ".WS_SESSIONS." SET ".
             "session_data = '".mysql_real_escape_string($session_data,$db->getLink())."' ".
             "WHERE session_id = '".mysql_real_escape_string($sid, $db->getLink())."';";
        $db->query($sql);
    } else {
        $expires = time() + SESSION_EXPIRE;
        $db->insert(WS_SESSIONS,
            array("session_id" => $sid,
                  "session_expires" => $expires,
                  "session_data" => $session_data),
            array("%s","%d","%s")
        );
    }
}

function uploadProfilePicture() {
    // check if we have a file
    if (empty($_FILES)) {
        respond(array(
            'success' => false,
            'message' => 'No file uploaded!'
        ));
    }

    if (empty($_REQUEST['userid'])) {
        respond(array(
            'success' => false,
            'message' => 'No user ID set!'
        ));
    }

    $ext = end(explode(".", $_FILES['profile']['name']));
    $tempFile = $_FILES['profile']['tmp_name'];
    $path = APP_IMAGE_PATH . $_REQUEST['userid'] . '.' . $ext;

    require_once('lib/S3/S3.php');

    try {
        File::s3Upload($tempFile, $path);

        $imgName = strtolower($_REQUEST['userid'] . '.' . $ext);
        $query = "
            UPDATE `" . USERS . "` 
            SET `picture` = '" . mysql_real_escape_string($imgName) . "' ,
            `s3bucket` = '" . S3_BUCKET ."'
            WHERE `id` = " . (int) $_REQUEST['userid'] . "
            LIMIT 1";

        if (! mysql_query($query)) {
            error_log("s3upload mysql: ".mysql_error());
            respond(array(
                'success' => false,
                'message' => SL_DB_FAILURE
            ));
        }

        respond(array(
            'success' => true,
            'picture' => $imgName
        ));

    } catch (Exception $e) {
        $success = false;
        $error = 'There was a problem uploading your file';
        error_log(__FILE__.": Error uploading images to S3:\n$e");
            
        return $this->setOutput(array(
            'success' => false,
            'message' => 'An error occured while uploading the file, please try again!'
        ));            
    }


}

function updateuser(){
    $sql = "UPDATE ".USERS." ".
           "SET ";
    $id = (int)$_REQUEST["user_id"];
    foreach($_REQUEST["user_data"] as $key => $value){
        $sql .= $key." = '".mysql_real_escape_string($value)."', ";
    }
    $sql = substr($sql,0,(strlen($sql) - 1));
    $sql .= " ".
            "WHERE id = ".$id;
    mysql_query($sql);
}

function pushVerifyUser(){
    $user_id = intval($_REQUEST['id']);
    $sql = "UPDATE " . USERS . " SET `confirm` = '1', is_active = '1' WHERE `id` = $user_id";
    mysql_unbuffered_query($sql);

    respond(array('success' => false, 'message' => 'User has been confirmed!'));
}

function updateProjectList(){
$repo = basename($_REQUEST['repo']);

$project = new Project();
$project->loadByRepo($repo);
$commit_date = date('Y-m-d H:i:s');
$project->setLastCommit($commit_date);
$project->save();

}

function getSystemDrawerJobs(){
    $objectDataReviews= array();
    $sql = " SELECT w.*, p.name as project "
         . " FROM   ". WORKLIST." AS w LEFT JOIN ". PROJECTS. " AS p "
         . " ON     (w.project_id = p.project_id) "
         . " WHERE  w.status = 'REVIEW' "
         . " AND w.code_review_completed = 0"
         . " AND w.code_review_started = 0"
         ;

    if ($result = mysql_query($sql)) {
        while ($row = mysql_fetch_assoc($result)) {
            $objectDataReviews[] = $row;
        }
    // Return our data array
    }
    mysql_free_result($result);

    $objectDataBidding= array();
    $sql = " SELECT w.*, p.name as project "
         . " FROM   ". WORKLIST." AS w LEFT JOIN ". PROJECTS. " AS p "
         . " ON     (w.project_id = p.project_id) "
         . " WHERE  w.status = 'BIDDING' OR w.status = 'SUGGESTEDwithBID' ";

    if ($result = mysql_query($sql)) {
        while ($row = mysql_fetch_assoc($result)) {
            $objectDataBidding[] = $row;
        }
    // Return our data array
    }
    mysql_free_result($result);

    respond(array('success' => true, 'review' => $objectDataReviews, 'bidding' => $objectDataBidding));
}

function sendBidNotification() {
    require_once('./classes/Notification.class.php');
    $notify = new Notification();
    $notify->emailExpiredBids();
}

function sendPastDueNotification() {
    require_once('./classes/Notification.class.php');
    $notify = new Notification();
    $notify->emailPastDueJobs();
}

function processW2Masspay() {
    if (!defined('COMMAND_API_KEY')
        or !array_key_exists('COMMAND_API_KEY',$_POST)
        or $_POST['COMMAND_API_KEY'] != COMMAND_API_KEY)
        { die('Action Not configured'); }

    $con = mysql_connect(DB_SERVER,DB_USER,DB_PASSWORD);
    if (!$con) {
        die('Could not connect: ' . mysql_error());
    }
    mysql_select_db(DB_NAME, $con);

    $sql = " UPDATE " . FEES . " AS f, " . WORKLIST . " AS w, " . USERS . " AS u "
         . " SET f.paid = 1, f.paid_date = NOW() "
         . " WHERE f.paid = 0 AND f.worklist_id = w.id AND w.status = 'DONE' "
         . "   AND f.withdrawn = 0 "
         . "   AND f.user_id = u.id "
         . "   AND u.has_W2 = 1 "
         . "   AND w.status_changed < CAST(DATE_FORMAT(NOW(),'%Y-%m-01') as DATE) "
         . "   AND f.date <  CAST(DATE_FORMAT(NOW() ,'%Y-%m-01') as DATE); ";

    // Marks all Fees from the past month as paid (for DONEd jobs)
    if (!$result = mysql_query($sql)) { error_log("mysql error: ".mysql_error()); die("mysql_error: ".mysql_error()); }
    $total = mysql_affected_rows();

    if( $total) {
        echo "{$total} fees were processed.";
    } else {
        echo "No fees were found!";
    }

    $sql = " UPDATE " . FEES . " AS f, " . USERS . " AS u "
         . " SET f.paid = 1, f.paid_date = NOW() "
         . " WHERE f.paid = 0 "
         . "   AND f.bonus = 1 "
         . "   AND f.withdrawn = 0 "
         . "   AND f.user_id = u.id "
         . "   AND u.has_W2 = 1 "
         . "   AND f.date <  CAST(DATE_FORMAT(NOW() ,'%Y-%m-01') as DATE); ";

    // Marks all Fees from the past month as paid (for DONEd jobs)
    if (!$result = mysql_query($sql)) { error_log("mysql error: ".mysql_error()); die("mysql_error: ".mysql_error()); }
    $total = mysql_affected_rows();

    if( $total) {
        echo "{$total} bonuses were processed.";
    } else {
        echo "No bonuses were found!";
    }
    mysql_close($con);
}

function doScanAssets() {
    require_once('./scanAssets.php');
    $scanner = new scanAssets();
    $scanner->scanAll();
}

function respond($val){
    exit(json_encode($val));
}

function sendContactEmail(){
    $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';
    $email = isset($_REQUEST['email']) ? $_REQUEST['email'] : '';
    $phone = isset($_REQUEST['phone']) ? $_REQUEST['phone'] : '';
    $proj_name = isset($_REQUEST['project']) ? $_REQUEST['project'] : '';
    $proj_desc = isset($_REQUEST['proj_desc']) ? $_REQUEST['proj_desc'] : '';
    if (empty($phone) || empty($email) || empty($phone) || empty($proj_name) || empty($proj_desc)) {
        exit(json_encode(array('error' => 'All Fields are required!')));
    }
    require_once('./classes/Notification.class.php');
    $notify = new Notification();
    if ($notify->emailContactForm($name, $email, $phone, $proj_name, $proj_desc)) {
        exit(json_encode(array('success' => true)));
    } else {
        exit(json_encode(array('error' => 'There was an error sending your message, please try again later.')));
    }
}// end sendContactEmail
function sendTestNotifications(){
    $workItemId = isset($_REQUEST['workitem_id']) ? $_REQUEST['workitem_id'] : 0 ;
    $results = isset($_REQUEST['results']) ? $_REQUEST['results'] : '';
    $revision = isset($_REQUEST['revision']) ? $_REQUEST['revision'] : '';
    require_once('./classes/Notification.class.php');
    if($workItemId > 0) {
        $notify = new Notification();
        $notify->autoTestNofications($workItemId,$results,$revision);
        exit(json_encode(array('success' => true)));
    }
}

function autoPassSuggestedJobs() {
    require_once('./autoPass.php');
    autoPassJobs();
}

function getTimezone() {
    if (isset($_REQUEST['username'])) {
        $username = $_REQUEST['username'];
    } else {
        respond(array('succeeded' => false, 'message' => 'Error: Could not determine the user'));
    }

    $user = new User();
    if ($user->findUserByUsername($username)) {
        respond(array('succeeded' => true, 'message' => $user->getTimezone()));
    } else {
        respond(array('succeeded' => false, 'message' => 'Error: Could not determine the user'));
    }
}

function updateLastSeen() {
    if (isset($_REQUEST['username'])) {
        $username = $_REQUEST['username'];
    } else {
        respond(array('succeeded' => false, 'message' => 'Error: Could not determine the user'));
    }
    $qry = "UPDATE ". USERS ." SET last_seen = NOW() WHERE username='". $username ."'";
    if ($res = mysql_query($qry)) {
        respond(array('succeeded' => true, 'message' => 'Last seen time updated!'));
    } else {
        respond(array('succeeded' => false, 'message' => mysql_error()));
    }
}
