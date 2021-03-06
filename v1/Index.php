<?php
 
require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';
 
\Slim\Slim::registerAutoloader();
 
$app = new \Slim\Slim();
// User id from db - Global Variable
$user_id = NULL;
 

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
	// Getting request headers
	$headers = apache_request_headers();
	$response = array();
	$app = \Slim\Slim::getInstance();

	// Verifying Authorization Header
	if (isset($headers['authorization'])) {
		$db = new DbHandler();

		// get the api key
		$api_key = $headers['authorization'];
		// validating api key
		if (!$db->isValidApiKey($api_key)) {
			// api key is not present in users table
			$response["error"] = true;
			$response["message"] = "Access Denied. Invalid Api key";
			echoRespnse(401, $response);
			$app->stop();
		} else {
			global $user_id;
			// get user primary key id
			$user = $db->getUserId($api_key);
			if ($user != NULL)
				$user_id = $user["idUser"];
		}
	} else {
		// api key is missing in header
		$response["error"] = true;
		$response["message"] = "Api key is misssing";
		echoRespnse(400, $response);
		$app->stop();
	}
}
/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('name', 'email', 'password'));

	$response = array();

	// reading post params
	$name_str = $app->request->post('name');
	$email_str = $app->request->post('email');
	$password_str = $app->request->post('password');
	$name = substr($name_str, 1, -1);
	$email = substr($email_str, 1, -1);
	$password = substr($password_str, 1, -1);
	// validating email address
	validateEmail($email);

	$db = new DbHandler();
	$res = $db->createUser($name, $email, $password);

	if ($res == USER_CREATED_SUCCESSFULLY) {
		$response["error"] = false;
		$response["message"] = "You are successfully registered";
		echoRespnse(201, $response);
	} else if ($res == USER_CREATE_FAILED) {
		$response["error"] = true;
		$response["message"] = "Oops! An error occurred while registereing";
		echoRespnse(200, $response);
	} else if ($res == USER_ALREADY_EXISTED) {
		$response["error"] = true;
		$response["message"] = "Sorry, this email already existed";
		echoRespnse(200, $response);
	}
});

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function() use ($app) {
	// check for required params
	
	verifyRequiredParams(array('email','password'));

	// reading post params
	$email_str = $app->request()->post('email');
	$password_str = $app->request()->post('password');
	$email = substr($email_str, 1, -1);
	$password = substr($password_str, 1, -1);
	$response = array();

	$db = new DbHandler();
	// check for correct email and password
	if ($db->checkLogin($email, $password)) {
		// get the user by email
		$user = $db->getUserByEmail($email);

		if ($user != NULL) {
// 			$response['error'] = false;
// 			$response['message'] = "An error not occurred. ";
			$response['idUser'] = $user['idUser'];
			$response["error"] = false;
			$response['name'] = $user['name'];
			$response['email'] = $user['email'];
			$response['apiKey'] = $user['apiKey'];
			$response['createdAt'] = $user['created_at'];
			
		} else {
			// unknown error occurred
			$response['error'] = true;
			$response['message'] = "An error occurred. Please try again";
		}
	} else {
		// user credentials are wrong
		
		$response['error'] = true;
		$response['message'] = "Login failed. Incorrect credentials $email $password";
	}

	echoRespnse(200, $response);
});



/**
 * Creating new plant in db
 * method POST
 * params - name
 * url - /tasks/
 */
$app->post('/plant', 'authenticate', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('name','latinName','description'));

	$response = array();
	$name = $app->request->post('name');
	$latinName = $app->request->post('latinName');
	$description = $app->request->post('description');
	global $user_id;
	$db = new DbHandler();

	// creating new task
	$idPlant = $db->createPlant($name, $latinName, $description);

	if ($idPlant != NULL) {
		$response["error"] = false;
		$response["message"] = "Plant created successfully";
		$response["idPlant"] = $idPlant;
	} else {
		$response["error"] = true;
		$response["message"] = "Failed to create plant. Please try again";
	}
	echoRespnse(201, $response);
});

	/**
	 * Uploading image file
	 * method POST
	 * url - /image/
	 */
$app->post('/image', 'authenticate', function() {
	// Path to move uploaded files
	$target_path = "../images/";
	$resized_path= "../images/resized/";
	// array for final json respone
	$response = array();
	
// 	// getting server ip address
// 	$server_ip = gethostbyname(gethostname());
	
// 	// final file url that is being uploaded
// 	$file_upload_url = 'http://192.168.0.101:8081/' . $target_path;
	
	if($_FILES['uploaded_file']['error']!=UPLOAD_ERR_OK){
		$response['error']=true;
		$response['message']='blad'.$_FILES['uploaded_file']['error'];
	}
	else {
		if (isset($_FILES['uploaded_file']['name'])) {
			$image_name = basename($_FILES['uploaded_file']['name']);
			$type = $_FILES['uploaded_file']['type'];
			$target_path = $target_path . $image_name;
			$resized_target_path = $resized_path . $image_name;
			$tmp_name = $_FILES['uploaded_file']['tmp_name'];
			$size2 = getimagesize($tmp_name);
			$width = $size2[0];
			$height = $size2[1];
			
		
			// reading other post parameters
			$plant_id = $_POST['idUserPlant'];
			
	// 		$website = isset($_POST['website']) ? $_POST['website'] : '';
		
			$response['file_name'] = basename($_FILES['uploaded_file']['name']);
			// $response['email'] = $email;
			// $response['website'] = $website;
		
			try {
				// Throws exception incase file is not being moved
				if (!move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $target_path)) {
					// make error flag true
					$response['error'] = true;
					$response['message'] = 'Could not move the file!';
				}
					$db = new DbHandler();
					$result = $db->updatePlantImage($image_name, $plant_id);
					// File successfully uploaded
					if($result){
						$response['message'] = "File uploaded successfully! plantid=$plant_id";
						$response['error'] = false;
						$response['file_path'] = basename($_FILES['uploaded_file']['name']);
						
					}
					else{
						$response['message'] = "File uploaded successfully but not updated in DB! plantid=$plant_id user=$user_id";
						$response['error'] = true;
					}
					
				
				
			} catch (Exception $e) {
				// Exception occurred. Make error flag true
				$response['error'] = true;
				$response['message'] = $e->getMessage();
			}
		} else {
			// File parameter is missing
			$response['error'] = true;
			$response['message'] = 'Not received any file!F';
		}
		// Echo final json response to client
		
		if($width == $height){
			$newwidth = 200;
			$newheight = 200;
		
		}
		if($width < $height){
			$newwidth = 200;
			$ratio = $newwidth/$width;
			$newheight = round($height*$ratio);
		}
		if($width > $height){
			$newheight = 200;
			$ratio = $newheight/$height;
			$newwidth = round($width*$ratio);
		}
		$response["message"] = $response["message"].$type;
		switch($type){
			case 'image/jpeg':
				$img = imagecreatefromjpeg($target_path);
				$resized_image = imagecreatetruecolor($newwidth, $newheight);
				imagecopyresized($resized_image, $img, 0,0,0,0,$newwidth, $newheight, $width, $height);
				imagejpeg($resized_image, $resized_target_path);
				break;
					
		}
	}
	echo json_encode($response);
	
	
	
});
	/**
	 * Listing all plants
	 * method GET
	 * url /plant
	 */
$app->get('/plant','authenticate', function() {
	
	$response = array();
	$db = new DbHandler();

	// fetching all user tasks
	$result = $db->getAllPlants();

	$response["error"] = false;
	$response["plants"] = array();

	// looping through result and preparing plants array
	while ($plant = $result->fetch_assoc()) {
		$tmp = array();
		$tmp["idPlant"] = $plant["idPlant"];
		$tmp["name"] = $plant["name"];
		$tmp["latinName"] = $plant["latinName"];
		$tmp["description"] = $plant["description"];
		array_push($response["plants"], $tmp);

	}

	echoRespnse(200, $response);
});
/**
 * Listing all plants of particual user
 * method GET
 * url /user_plants
 */
$app->get('/user_plants', 'authenticate', function() {
	global $user_id;
	$response = array();
	$db = new DbHandler();

	// fetching all user plants
	$result = $db->getAllUserPlants($user_id);
	
	if ($result != NULL) {
		$response["error"] = false;
		$response["plants"] = array();
		
		// looping through result and preparing tasks array
		while ($plant = $result->fetch_assoc()) {
			$tmp = array();
			$tmp["idPlant"] = $plant["idPlant"];
			$tmp["idUserPlant"] = $plant["idUserPlant"];
			$tmp["name"] = $plant["name"];
			$tmp["latinName"] = $plant["latinName"];
			$tmp["description"] = $plant["description"];
			$tmp["imageAdress"] = $plant["imageAdress"];
			$tmp["location"] = $plant["location"];
			$tmp["created_at"] = $plant["created_at"];
			array_push($response["plants"], $tmp);
			
		}
		echoRespnse(200, $response);
	} else {
		$response["error"] = true;
		$response["message"] = "The requested resource doesn't exists";
		echoRespnse(200, $response);
	}
});
	$app->get('/user_reminds', 'authenticate', function() {
		global $user_id;
		$response = array();
		$db = new DbHandler();
	
		// fetching all user plants
		$result = $db->getAllUserReminds($user_id);
	
		if ($result != NULL) {
// 			$response["error"] = false;
			
	
			// looping through result and preparing tasks array
			while ($plant = $result->fetch_assoc()) {
				$tmp = array();
				$tmp["idRemind"] = $plant["idRemind"];
				$tmp["idUserPlant"] = $plant["idUserPlant"];
				$tmp["date"] = $plant["date"];
				$tmp["latinName"] = $plant["latinName"];
				$tmp["plantName"] = $plant["plantName"];
				$tmp["name"] = $plant["name"];
				$tmp["imageAdress"] = $plant["imageAdress"];
				$tmp["location"] = $plant["location"];
				$tmp["type"] = $plant["type"];
				array_push($response, $tmp);
					
			}
			echoRespnse(200, $response);
		} else {
			$response["error"] = true;
			$response["message"] = "The requested resource doesn't exists";
			echoRespnse(200, $response);
		}
});
$app->get('/user_reminds/:id', 'authenticate', function($idUserPlant) {
	global $user_id;
	$response = array();
	$db = new DbHandler();

	// fetching all user plants
	$result = $db->getUserPlantReminds($user_id, $idUserPlant);

	if ($result != NULL) {
		// 			$response["error"] = false;
			

		// looping through result and preparing tasks array
		while ($plant = $result->fetch_assoc()) {
			$tmp = array();
			$tmp["idRemind"] = $plant["idRemind"];
			$tmp["idUserPlant"] = $plant["idUserPlant"];
			$tmp["date"] = $plant["date"];
			$tmp["latinName"] = $plant["latinName"];
			$tmp["plantName"] = $plant["plantName"];
			$tmp["name"] = $plant["name"];
			$tmp["imageAdress"] = $plant["imageAdress"];
			$tmp["location"] = $plant["location"];
			$tmp["type"] = $plant["type"];
			array_push($response, $tmp);
				
		}
		echoRespnse(200, $response);
	} else {
		$response["error"] = true;
		$response["message"] = "The requested resource doesn't exists";
		echoRespnse(200, $response);
	}
});
$app->post('/user_plants', 'authenticate', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('idPlant'));

	$response = array();
	global $user_id;
	$idPlant = $app->request->post('idPlant');

	$db = new DbHandler();

	// creating new task
	$created = $db->createUserPlant($idPlant, $user_id);

	if ($created) {
		$response["error"] = false;
		$response["message"] = "Plant created successfully";
		$response["idPlant"] = $idPlant;
	} else {
		$response["error"] = true;
		$response["message"] = "Failed to create plant. Please try again";
	}
	echoRespnse(201, $response);
});
$app->post('/user_reminds','authenticate', function() use ($app) {
	// check for required params
	verifyRequiredParams(array('idUserPlant','idAction','date', 'type'));

	$response = array();
	
	$idUserPlant = $app->request->post('idUserPlant');
	$idAction = $app->request->post('idAction');
	$date = $app->request->post('date');
	$type = $app->request->post('type');
	
	
	
	$db = new DbHandler();

	
// creating new task
	$created = $db->addNewRemind($idUserPlant, $idAction, $date, $type);

	if ($created) {
		$response["error"] = false;
		$response["message"] = "Plant created successfully";
		
	} else {
		$response["error"] = true;
		$response["message"] = "Failed to create plant. Please try again";
	}
	
	
	echoRespnse(201, $response);
});
/**
 * Listing single plant
 * method GET
 * url /tasks/:id

 */
$app->get('/plant/:id', 'authenticate', function($idPlant) {
	
	$response = array();
	$db = new DbHandler();

	// fetch task
	$result = $db->getPlant($idPlant);

	if ($result != NULL) {
		$response["error"] = false;
		$response["idPlant"] = $result["idPlant"];
		$response["name"] = $result["name"];
		$response["latinName"] = $result["latinName"];
		$response["description"] = $result["description"];
		echoRespnse(200, $response);
	} else {
		$response["error"] = true;
		$response["message"] = "The requested resource doesn't exists";
		echoRespnse(404, $response);
	}
});
	

/**
 * Listing single action of particual user
 * method GET
 * url /action/:id
 */
$app->get('/action',  function() {
global $user_id;
	$response = array();
	$db = new DbHandler();

	// fetching all user plants
	$result = $db->getAction();

	if ($result != NULL) {

		

		// looping through result and preparing tasks array
		while ($plant = $result->fetch_assoc()) {
			$tmp = array();
			$tmp["idAction"] = $plant["idAction"];
			$tmp["name"] = $plant["name"];
			
			array_push($response, $tmp);
				
		}
		echoRespnse(200, $response);
	} else {
		$response["error"] = true;
		$response["message"] = "The requested resource doesn't exists";
		echoRespnse(200, $response);
	}
});
/**
 * Updating existing remind
 * method PUT
 * params idRemind, data
 * url - /user_reminds/:id
 */
$app->put('/user_reminds/:id', 'authenticate', function($idRemind) use($app) {
	// check for required params
	verifyRequiredParams(array('date'));

	$data = $app->request->put('date');
	
	
	$db = new DbHandler();
	$response = array();

	// updating remind
	$result = $db->updateRemind($idRemind, $data);
	if ($result) {
		// remind updated successfully
		$response["error"] = false;
		$response["message"] = "Remind updated successfully";
	} else {
		// remind failed to update
		$response["error"] = true;
		$response["message"] = "Remind failed to update. Please try again!$data $idRemind";
	}
	echoRespnse(200, $response);
});
/**
 * Updating existing user_plant
 * method PUT
 * params idUserPlant, location
 * url - /user_plants/:id
 */
$app->put('/user_plants/:id', 'authenticate', function($idUserPlant) use($app) {
	// check for required params
	verifyRequiredParams(array('location'));

	$location = $app->request->put('location');
// 	$location = "Korytarz";
	$db = new DbHandler();
	$response = array();


	$result = $db->updateLocation($idUserPlant, $location);
	if ($result) {
		// remind updated successfully
		$response["error"] = false;
		$response["message"] = "updated successfully";
	} else {
		// remind failed to update
		$response["error"] = true;
		$response["message"] = "failed to update. Please try again!";
	}
	echoRespnse(200, $response);
});

	

/**
 * Deleting remind. Users can delete only their reminds
 * method DELETE
 * url /user_reminds/:id
 */
$app->delete('/user_reminds/:id', 'authenticate', function($id) use($app) {

	$db = new DbHandler();
	$response = array();
	$result = $db->deleteRemind($id);
	if ($result) {
		// task deleted successfully
		$response["error"] = false;
		$response["message"] = "Remind deleted succesfully";
	} else {
		// task failed to delete
		$response["error"] = true;
		$response["message"] = "Remind failed to delete. Please try again!";
	}
	echoRespnse(200, $response);
});

/**
 * Deleting userplant. 
 * method DELETE
 * url /user_plants
 */
$app->delete('/user_plants/:id', 'authenticate', function($id) use($app) {
	

	$db = new DbHandler();
	$response = array();
	$result = $db->deleteUserPlant($id);
	if ($result) {
		// Plant deleted successfully
		$response["error"] = false;
		$response["message"] = "Plant deleted succesfully";
	} else {
		// Plant failed to delete
		$response["error"] = true;
		$response["message"] = "Plant failed to delete. Please try again!";
	}
	echoRespnse(200, $response);
});


/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
 
    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}
 
/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
 
    // setting response content type to json
    $app->contentType('application/json');
    $app->language('pl');
    $app->charset('utf-8');
    
    echo json_encode($response);
}

$app->run();
?>