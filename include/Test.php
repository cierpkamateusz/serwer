<?php
use PHPUnit\Framework\TestCase;
require_once 'DbHandler.php';
require_once 'PassHash.php';
require_once 'Config.php';

 

class ExampleTest extends PHPUnit_Framework_TestCase {

    public function testGreetings()
    {
        $greetings = 'Hello World';
        $this->assertEquals('Hello World', $greetings);
    }
	
	public function testLogin()
    {
        $db = new DbHandler();

		$email = "test@test.pl";
		$password = "test";
		$this->assertTrue($db->checkLogin($email, $password));
    }
    public function testCreateUserPlant(){
    	$db = new DbHandler();
    	
    	
    }
    public function testGetAndDeleteUserPlant(){
    	$db = new DbHandler();
    	$idPlant = 3; //Krokus
    	$idUser = 2; //test@test.pl
    	$this->assertTrue($db->createUserPlant($idPlant, $idUser));
    	$result = $db->getAllUserPlants($idUser);
    	$plant = $result->fetch_assoc();
    	$this->assertTrue($db->deleteUserPlant($plant["idUserPlant"]));
    }
    
    public function testCreateGetDeleteUserPlantRemind(){
    	$db = new DbHandler();
    	$idPlant = 3; //Krokus
    	$idUser = 2; //test@test.pl
    	$idAction = 2;
    	$date = "2016-12-12";
    	$type = "d";
    	$db->createUserPlant($idPlant, $idUser);
    	$plants = $db->getAllUserPlants($idUser);
    	$plant = $plants->fetch_assoc();
    	
    	$idUserPlant = $plant["idUserPlant"];
    	$remind = $db->addNewRemind($idUserPlant, $idAction, $date, $type);
    	echo($remind);
    	
    	$this->assertTrue($db->deleteRemind($remind));
    	$this->assertTrue($db->deleteUserPlant($plant["idUserPlant"]));
    }
   

}

?>