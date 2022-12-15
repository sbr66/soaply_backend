<?php

include_once $_SERVER['DOCUMENT_ROOT'].'/main_backend/connect/dbconn.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['req_sign'] == 'signup'){
    signup($conn);
}

function signup($conn){ // 회원가입 처리 함수
    // 포스트 변수 할당
    $name = $_POST['name'];
    $id = $_POST['id'];
    $email = $_POST['email'];
    $pwd = $_POST['pwd'];

    $pwd = password_hash($pwd, PASSWORD_DEFAULT);

    // echo json_encode(array("name" => var_dump($conn)));

    // sql 입력 명령어 작성
    $sql = "INSERT INTO spl_user (user_name, user_id, user_email, user_pass) VALUES (?,?,?,?)";

    //stmt init 참조 :https://www.w3schools.com/Php/func_mysqli_stmt_init.asp
    $stmt = $conn->stmt_init();

    if(!$stmt->prepare($sql)){
        http_response_code(400);
        echo json_encode(array("err message" => "Database insert fail."));
    }

    $stmt -> bind_param("ssss", $name, $id, $email, $pwd);
    $stmt -> execute();
    
    if($stmt->affected_rows > 0){
        http_response_code(200);
        echo json_encode(array("msg" => "Sign UP Successfully!"));
    }else{
        http_response_code(400);
        echo json_encode(array("msg"=>"Sign Up fail!"));
    }

    // echo json_encode(array("name" => var_dump($stmt)));
    
    // echo json_encode(array("name" => $name, "id" => $id, "email" => $email, "pwd" => $pwd)); // 문자열을 json 변수로 반환한다. 파라미터에는 반드시 배열이 있어야한다.
}
function signin(){} // 로그인 처리 함수
function signout(){} // 로그아웃 처리 함수
?>