<?php
 
/**
 * Class to handle all db operations
 * This class will have CRUD methods for database tables
 *
 * @author Ravi Tamada
 */
class DbHandler {
 
    private $conn;
 
    function __construct() {
        require_once dirname(__FILE__) . './DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }
 
    /* ------------- `users` table method ------------------ */
 
    /**
     * Creating new user
     * @param String $name User full name
     * @param String $email User login email id
     * @param String $password User login password
     */
    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';
        $response = array();
 
        // First check if user already existed in db
        if (!$this->isUserExists($email)) {
            // Generating password hash
            $encryptedPassword = PassHash::hash($password);
 
            // Generating API key
            $api_key = $this->generateApiKey();
 
            // insert query
            $stmt = $this->conn->prepare("INSERT INTO user(name, email, encryptedPassword, apiKey, status) values(?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $name, $email, $encryptedPassword, $api_key);
 
            $result = $stmt->execute();
 
            $stmt->close();
 
            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
            } else {
                // Failed to create user
                return USER_CREATE_FAILED;
            }
        } else {
            // User with same email already existed in the db
            return USER_ALREADY_EXISTED;
        }
 
        return $response;
    }
 
    /**
     * Checking user login
     * @param String $email User login email id
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($email, $password) {
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT encryptedPassword FROM user WHERE email = ?");
 
        $stmt->bind_param("s", $email);
 
        $stmt->execute();
 
        $stmt->bind_result($encryptedPassword);
 
        $stmt->store_result();
 
        if ($stmt->num_rows() > 0) {
            // Found user with the email
            // Now verify the password
 
            $stmt->fetch();
 
            $stmt->close();
 
            if (PassHash::check_password($encryptedPassword, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();
 
            // user not existed with the email
            return FALSE;
        }
    }
 
    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT idUser from user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }
 
    /**
     * Fetching user by email
     * @param String $email User email id
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT idUser, name, email, apiKey, status, created_at FROM user WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }
 
    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     */
    public function getApiKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM user WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $api_key = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
    }
 
    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     */
    public function getUserId($apiKey) {
        $stmt = $this->conn->prepare("SELECT idUser FROM user WHERE apiKey = ?");
        $stmt->bind_param("s", $apiKey);
        if ($stmt->execute()) {
            $user_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }
 
    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $apiKey user api key
     * @return boolean
     */
    public function isValidApiKey($apiKey) {
        $stmt = $this->conn->prepare("SELECT idUser from user WHERE apiKey = ?");
        $stmt->bind_param("s", $apiKey);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }
 
    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }
 
    /* ------------- `tasks` table method ------------------ */
 
    /**
     * Creating new task
     * @param String $user_id user id to whom task belongs to
     * @param String $task task text
     */
    public function createTask($user_id, $task) {        
        $stmt = $this->conn->prepare("INSERT INTO tasks(task) VALUES(?)");
        $stmt->bind_param("s", $task);
        $result = $stmt->execute();
        $stmt->close();
 
        if ($result) {
            // task row created
            // now assign the task to user
            $new_task_id = $this->conn->insert_id;
            $res = $this->createUserTask($user_id, $new_task_id);
            if ($res) {
                // task created successfully
                return $new_task_id;
            } else {
                // task failed to create
                return NULL;
            }
        } else {
            // task failed to create
            return NULL;
        }
    }
    /**
     * Creating new plant
     * @param String $name name of new plant
     * @param String $latinName name in latin
     * @param String $description description of new plant
     */
    public function createPlant($name, $latinName, $description) {
    	$stmt = $this->conn->prepare("INSERT INTO plant(name, latinName, description) VALUES(?,?,?)");
    	$stmt->bind_param("sss", $name, $latinName, $description);
    	$result = $stmt->execute();
    	$stmt->close();
    
    	if ($result) {
    		// plant created successfully
    		return $this->conn->insert_id;
    		} 
    	else {
    		// plant failed to create
    		return NULL;
    	}
    }
    /**
     * Fetching single plant
     * @param String $idPlant id of the plant
     */
    public function getPlant($idPlant) {
    	$stmt = $this->conn->prepare("SELECT idPlant, name, latinName, description from plant WHERE idPlant = ? ");
    	$stmt->bind_param("i", $idPlant);
    	if ($stmt->execute()) {
    		$task = $stmt->get_result()->fetch_assoc();
    		$stmt->close();
    		return $task;
    	} else {
    		return NULL;
    	}
    }
    /**
     * Fetching single task
     * @param String $task_id id of the task
     */
    public function getAction() {
    	$stmt = $this->conn->prepare("SELECT * FROM action_type");
    	 
    	if ($stmt->execute()) {
    		$action = $stmt->get_result();
    		$stmt->close();
    		return $action;
    	} else {
    		return NULL;
    	}
    }
    /**
     * Fetching single task
     * @param String $task_id id of the task
     */
    public function getTask() {
        $stmt = $this->conn->prepare("SELECT * FROM table1");
       
        if ($stmt->execute()) {
            $task = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $task;
        } else {
            return NULL;
        }
    }
    /**
     * Fetching all plants
     */
    public function getAllPlants() {
    	
    	$stmt = $this->conn->prepare("SELECT * FROM plant");
    	$stmt->execute();
    	$plants = $stmt->get_result();
    	$stmt->close();
    	return $plants;
    }
    /**
     * Fetching all user plants
     * @param String $idUser id of the user
     */
    public function getAllUserPlants($idUser) {
    	$stmt = $this->conn->prepare("SELECT p.*, idUserPlant, imageAdress, location, created_at FROM plant p, user_plants up WHERE p.idPlant = up.idPlant AND up.idUser = ?");
    	$stmt->bind_param("i", $idUser);
    	$stmt->execute();
    	$plants = $stmt->get_result();
    	$stmt->close();
    	return $plants;
    }
    /**
     * Fetching all user reminds
     * @param String $idUser id of the user
     */
    public function getAllUserReminds($idUser) {
    	$stmt = $this->conn->prepare("SELECT r.idRemind, r.date, r.type, up.idUserPlant, up.imageAdress, up.location, p.name as plantName, p.latinName, a.name
										FROM remind r 
										JOIN user_plants up 
										ON r.idUserPlant = up.idUserPlant
										JOIN plant p
										ON up.idPlant = p.idPlant
										JOIN action_type a
										ON r.idAction = a.idAction
    									WHERE up.idUser = ?
    									ORDER BY r.date");
    	$stmt->bind_param("i", $idUser);
    	$stmt->execute();
    	$reminds = $stmt->get_result();
    	$stmt->close();
    	return $reminds;
    }
    /**
     * Fetching all user reminds
     * @param String $idUser id of the user
     */
    public function getUserPlantReminds($idUser, $idUserPlant) {
    	$stmt = $this->conn->prepare("SELECT r.idRemind, r.date, r.type, up.idUserPlant, up.imageAdress, up.location, p.name as plantName, p.latinName, a.name
										FROM remind r
										JOIN user_plants up
										ON r.idUserPlant = up.idUserPlant
										JOIN plant p
										ON up.idPlant = p.idPlant
										JOIN action_type a
										ON r.idAction = a.idAction
    									WHERE up.idUser = ?
    									AND up.idUserPlant = ?");
    	$stmt->bind_param("ii", $idUser, $idUserPlant);
    	$stmt->execute();
    	$reminds = $stmt->get_result();
    	$stmt->close();
    	return $reminds;
    }
    /**
     * adding potrawa to zamowienie
     */
    public function addNewRemind($idUserPlant, $idAction, $date, $type) {
    	

    	
    	$correct_date = substr($date, 1, -1);
		$correct_type = substr($type, 1, -1);
    	$stmt = $this->conn->prepare("INSERT INTO remind (idUserPlant, idAction, date, type) VALUES(?,?,?,?)");
    	$stmt->bind_param("iiss", $idUserPlant, $idAction, $correct_date, $correct_type);
    	$result = $stmt->execute();
    	$stmt->close();
    
    	if ($result) {
    		// plant created successfully
    		return $this->conn->insert_id;
    	}
    	else {
    		// plant failed to create
    		return NULL;
    	}
    }
    /**
     * Creating new user plant
     * @param String $idPlant id of new plant
     * @param String $idUser id of user
     */
    public function createUserPlant($idPlant, $idUser) {
    	$stmt = $this->conn->prepare("INSERT INTO user_plants(idPlant, idUser) VALUES(?,?)");
    	$stmt->bind_param("ss", $idPlant, $idUser);
    	$result = $stmt->execute();
    	$stmt->close();
    
    	if ($result) {
    		// plant created successfully
    		return TRUE;
    	}
    	else {
    		// plant failed to create
    		return NULL;
    	}
    }
    /**
     * Fetching all user tasks
     * @param String $user_id id of the user
     */
    public function getAllUserTasks($user_id) {
        $stmt = $this->conn->prepare("SELECT t.* FROM tasks t, user_tasks ut WHERE t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }
 
    /**
     * Updating task
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateTask($user_id, $task_id, $task, $status) {
        $stmt = $this->conn->prepare("UPDATE tasks t, user_tasks ut set t.task = ?, t.status = ? WHERE t.id = ? AND t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("siii", $task, $status, $task_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }
    
    /**
     * Updating remind date
     * @param String $task_id id of the task
     * @param String $task task text
     * @param String $status task status
     */
    public function updateRemind($idRemind, $date) {
    	$stmt = $this->conn->prepare("UPDATE remind set date = ? WHERE idRemind = ?");
    	$stmt->bind_param("si", $date, $idRemind);
    	$stmt->execute();
    	$num_affected_rows = $stmt->affected_rows;
    	$stmt->close();
    	return $num_affected_rows > 0;
    }
    /**
     * Updating location
     */
    public function updateLocation($idUserPlant, $location) {
    	$stmt = $this->conn->prepare("UPDATE user_plants set location = ? WHERE idUserPlant = ?");
    	$stmt->bind_param("si", $location, $idUserPlant);
    	$stmt->execute();
    	$num_affected_rows = $stmt->affected_rows;
    	$stmt->close();
    	return $num_affected_rows > 0;
    }
    /**
     * Updating imageAdress
     * @param String $imageAdress name of the image
     * @param String $user_id 
     * @param String $plant_id
     */
    public function updatePlantImage($imageAdress, $user_id) {
    	$stmt = $this->conn->prepare("UPDATE user_plants set imageAdress = ? WHERE idUserPlant = ?");
    	$stmt->bind_param("si", $imageAdress, $user_id);
    	$stmt->execute();
    	$num_affected_rows = $stmt->affected_rows;
    	$stmt->close();
    	return $num_affected_rows > 0;
    }
    
    /**
     * Deleting a task
     * @param String $id id of the task to delete
     */
    public function deleteRemind($id) {
    	$stmt = $this->conn->prepare("DELETE FROM remind WHERE idRemind = ?");
    	$stmt->bind_param("i", $id);
    	$stmt->execute();
    	$num_affected_rows = $stmt->affected_rows;
    	$stmt->close();
    	return $num_affected_rows > 0;
    }
    /**
     * Deleting a task
     * @param String $task_id id of the task to delete
     */
    public function deleteTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("DELETE t FROM tasks t, user_tasks ut WHERE t.id = ? AND ut.task_id = t.id AND ut.user_id = ?");
        $stmt->bind_param("ii", $task_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }
    /**
     * Deleting a task
     * @param String $task_id id of the task to delete
     */
    public function deleteUserPlant($plant_id) {
    	$stmt = $this->conn->prepare("DELETE FROM user_plants WHERE idUserPlant = ?");
    	$stmt->bind_param("i", $plant_id);
    	$stmt->execute();
    	$num_affected_rows = $stmt->affected_rows;
    	$stmt->close();
    	return $num_affected_rows > 0;
    }
 
    /* ------------- `user_tasks` table method ------------------ */
 
    /**
     * Function to assign a task to user
     * @param String $user_id id of the user
     * @param String $task_id id of the task
     */
    public function createUserTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_tasks(user_id, task_id) values(?, ?)");
        $stmt->bind_param("ii", $user_id, $task_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
 
}
 
?>