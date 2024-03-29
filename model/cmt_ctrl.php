<?php
    
    include $_SERVER['DOCUMENT_ROOT'].'/soaply_backend/etc/error.php';
    include_once $_SERVER['DOCUMENT_ROOT'].'/soaply_backend/connect/dbconn.php'; // 연결한 dbconn.php에 session start가 있음 => 현재 페이지에 session start 할 필요 X

    function parse_raw_http_request(array &$a_data){
        // read incoming data
        $input = file_get_contents('php://input');
        
        // grab multipart boundary from content type header
        preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
        $boundary = $matches[1];
        
        // split content by boundary and get rid of last -- element
        $a_blocks = preg_split("/-+$boundary/", $input);
        array_pop($a_blocks);
            
        // loop data blocks
        foreach ($a_blocks as $id => $block)
        {
            if (empty($block))
            continue;
            
            // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char
            
            // parse uploaded files
            if (strpos($block, 'application/octet-stream') !== FALSE)
            {
            // match "name", then everything after "stream" (optional) except for prepending newlines 
            preg_match('/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s', $block, $matches);
            }
            // parse all other fields
            else
            {
            // match "name" and optional value in between newline sequences
            preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
            }
            $a_data[$matches[1]] = $matches[2];
        }        
    }

    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['req_sign']) && $_GET['req_sign'] == 'post_cmt'){
        post_cmt($conn);
    }
    
    if($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['req_sign']) && $_GET['req_sign'] == 'get_cmt'){
        get_cmt($conn);
    }
    
    if($_SERVER['REQUEST_METHOD'] == 'PATCH' && isset($_GET['req_sign']) && $_GET['req_sign'] == 'patch_cmt'){
        patch_cmt($conn);
    }    


    function post_cmt($conn){
        if(isset($_SESSION['useridx'])){
            $u_idx = $_SESSION['useridx'];
            
        }else{
            $u_idx='';
        }

        $pro_idx = $_GET['p_idx'];
        $content = $_POST['cmt_cont']; // details.html > comment-form > form > textarea 의 name값
        $cmt_reg=date("Y-m-d H:i:s");

        if(!isset($_POST['cmt_star'])){
            $cmt_star = 1;
        } else {
            $cmt_star = $_POST['cmt_star'];
        }

        if(!isset($_SESSION['useridx'])){
            echo json_encode(array("msg" => "상품평을 작성하려면 로그인이 필요합니다."));
            exit();
        }

        // sql 입력 명령어 작성
        $sql = "INSERT INTO spl_cmt (cmt_u_idx, cmt_pro_idx, cmt_cont, cmt_reg, cmt_star) VALUES (?,?,?,?,?)";

        //stmt init 참조 :https://www.w3schools.com/Php/func_mysqli_stmt_init.asp
        $stmt = $conn->stmt_init();

        if(!$stmt->prepare($sql)){
            http_response_code(400);
            echo json_encode(array("msg" => "상품평 입력이 되지 않았습니다."));
        }

        $stmt -> bind_param("sssss", $u_idx, $pro_idx, $content, $cmt_reg, $cmt_star);
        $stmt -> execute();
        
        if($stmt->affected_rows > 0){
            http_response_code(200);
            echo json_encode(array("msg" => "상품평이 게시되었습니다."));
        }else{
            http_response_code(400);
            echo json_encode(array("msg"=>"상품평 입력이 되지 않았습니다."));
        }

    }

    // 상품 조회
    function get_cmt($conn){

        $p_idx = $_GET['p_idx'];

        if(isset($_SESSION['userid'])){
            $userid=$userid = $_SESSION['userid'];
        }else{
            $userid="guest";
        }
       

        // spl_cmt 테이블 전체 데이터와 spl_user 테이블의 아이디를 조회한다. (두 개의 테이블 데이터를 동시 조회하기 위해서는 테이블간 join이 필요하다.)
        // join 참조 : https://pearlluck.tistory.com/46
        // 조회된 데이터는 파라미터의 상품 데이터에 한정한다.
        // 조회 결과는 시간의 역순, 즉 최신순으로 나열한다.
        $sql = "SELECT spl_cmt.*, spl_user.user_id FROM spl_cmt JOIN spl_user ON spl_cmt.cmt_u_idx = spl_user.user_idx WHERE cmt_pro_idx = $p_idx ORDER BY spl_cmt.cmt_reg DESC";
        $result = mysqli_query($conn, $sql);

        // 특정 컬럼 평균값 구하기
        // SELECT AVG ([column name]) FROM [table name] WHERE [condition]
        $sql_avg = "SELECT AVG(cmt_star) as avg FROM spl_cmt WHERE cmt_pro_idx = $p_idx";
        $avg_result = mysqli_query($conn, $sql_avg);
        $avg_arr = mysqli_fetch_array($avg_result)['avg']; // 인덱스빼고 키값으로만 출력

        if(!mysqli_num_rows($result)){
             echo json_encode(array("msg" => "조회된 게시글이 없습니다.", "avg" => 0));
            exit();
        }else{
            $json_result = array(); // 빈 배열 초기화
            while($row = mysqli_fetch_array($result)){
                array_push($json_result, array("cmt_idx" => $row['cmt_idx'], "cmt_cont" => $row['cmt_cont'], "cmt_reg" => $row['cmt_reg'], "user_id" => $row['user_id'], "session_id" => $userid, "rating" => $row['cmt_star'], "avg" => $avg_arr)); // 첫번째 파라미터: 대상 배열, 두번째 파라미터는 배열 입력값
            }
        }

        echo json_encode($json_result);
    }
    function patch_cmt($conn){
        // update 구문 참조 : http://www.tcpschool.com/mysql/mysql_basic_update

        $_PATCH = [];
        parse_str(file_get_contents('php://input'), $_PATCH);
        parse_raw_http_request($_PATCH);
        // php patch 관련 참조 : https://stackoverflow.com/questions/20572639/get-patch-request-data-in-php


        $cmt_idx = $_GET['cmt_idx'];
        $cmt_cont = $_PATCH['update_cont'];
        $cmt_star= $_PATCH['cmt_star'];
        // php에서는 공식적으로 post와 get만 지원한다. 따라서 patch, delete, put 등은 별도의 접근 처리를 해줘야 한다.

        if(!isset($_SESSION['useridx'])){
            echo json_encode(array("msg" => "작성한 본인이 아니면 수정할 수 없습니다."));
            exit();
        }

        $sql = "UPDATE spl_cmt SET cmt_cont = ?, cmt_star = ? WHERE cmt_idx = ?";
        $stmt = $conn->stmt_init(); // php 7.4 버전 이상에서만 가능

        if(!$stmt->prepare($sql)){
            http_response_code(400);
            echo json_encode(array("msg" => "글 수정에 실패했습니다.1"));
        }

        $stmt -> bind_param("sss", $cmt_cont, $cmt_star, $cmt_idx); // 순서 맞춰주기
        $stmt -> execute();
        

            echo json_encode(array("msg" => "상품평이 수정되었습니다."));

    }
?>