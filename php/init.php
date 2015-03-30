<?php

$timezones = array(
    "local" =>  new DateTimeZone("Asia/Almaty"),
    "global" => new DateTimeZone("Europe/London"),
    "server" => new DateTimeZone("Europe/Amsterdam")
);

$admin_mail = "andreiprostoandrei@gmail.com";
function home_dir($path){
    return "/home/webinaro/public_html/".$path;
}



// this function convert human-entered and generated by backend local time into global

function date_ltg($input){
    global $timezones;
    $date = new DateTime($input, $timezones["local"]);
    $date->setTimeZone($timezones["global"]);
    return date_format($date, 'Y-m-d H:i:s');
}

// this function converts stored in global time dates into local to show to users
function date_gtl($input, $angular_format){
    global $timezones;
    $date = new DateTime($input, $timezones["global"]);
    $date->setTimeZone($timezones["local"]);
    if($angular_format == true){
        return date_format($date, 'c');
    }else{
        return date_format($date, 'Y-m-d H:i:s');

    }
}

// this function converts generated by script date (with server default time) into global
function date_stg($input){
    global $timezones;
    $date = new DateTime($input, $timezones["server"]);
    $date->setTimeZone($timezones["global"]);
    return date_format($date, 'Y-m-d H:i:s');
}
function angular_time($str) {
    $date = new DateTime($str);
    return date("c", $date->getTimestamp());
}



//testing if the var exists
function grain_test_var($input, $default_output){
    if(gettype($input) == NULL){
        return $default_output;
    }else{
        return $input;
    }
}


//init database
$the_base = new PDO(
    'mysql:host=localhost;dbname=webinaro_grain;charset=utf8',
    'webinaro_grain',
    'kaSC836dPvzT5br6',
    array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
);








// auth check here //
if($free_query != true) {
    // test if



    $user = array(
        "id" => 0,
        "auth_key" => "",
        "role" => 0
    );
    if (
        isset($_COOKIE['user_id'])
        and
        $_COOKIE['user_id'] != ""
        and
        isset($_COOKIE['auth_key'])
        and
        $_COOKIE['auth_key'] != ""
        and
        isset($_COOKIE['user_role'])
        and
        $_COOKIE['user_role'] != ""
    ) {
        $user = array(
            "id" => intval($_COOKIE['user_id']),
            "auth_key" => $_COOKIE['auth_key'],
            "role" => $_COOKIE['user_role']
        );
    } else {
        grain_status(
            "error",
            array("message" => "auth_mismatch"),
            "authorisation_error: request type is undefined"
        );

    }
    $check_session_query = $the_base->prepare("SELECT `auth_key`, `role` FROM `users` WHERE `id` = " . $user['id']);
    $check_session_query->execute();


    if ($check_session_query->rowCount() == 1) {

        $check_session_result = $check_session_query->fetch(PDO::FETCH_ASSOC);
        if (
            $check_session_result["auth_key"] == $user['auth_key']
            and
            $check_session_result["role"] == $_COOKIE['user_role']
        ) {

        } else {

            grain_status(
                "surprise",
                array(
                    "message" => "auth_mismatch",
                    "extra" => "cookie: ".$user['auth_key']." :: db: ".$check_session_result["auth_key"]
                ),
                "auth_key is not equal to stored for " . $user['id']
            );
        }
    } else {
        grain_status(
            "error",
            array("message" => "auth_mismatch"),
            "unexpected users count on checking session of user " . $user['id']
        );
    }
}
// / auth check //













//grain status function
function grain_status(
    $the_status,    // = "ok" / "surprise" / "error"
    $the_output,    // = JSON object to send to front end   (in case of error or surprise: { "message" : "error_identification" }    )
    $the_message    // = message to show ONLY admin
){
    global $admin_mail;

    //on error
    if($the_status == "error"){
        mail($admin_mail, "grain error", $the_message);
    }


    //send information and finish the program
    exit (
        json_encode(
            array(
                "status"    =>   $the_status,
                "response"  =>   $the_output
            ),
            JSON_UNESCAPED_UNICODE
        )
    );
}










function add_notifications($notifications, $task, $comment){


    global $user, $the_base, $admin_mail;


    // mail($admin_mail, "---", count($notifications));
    if(count($notifications) > 0){

        $add_notifications_query_text = "INSERT INTO `notifications` (`task`,`comment`,`notified`,`notifier`) VALUES ";
        foreach($notifications as $n){
            $add_notifications_query_text .= "(".$task.", '".$comment."', ".$n.", ".$user["id"]." ),";
        }
        $add_notifications_query_text = rtrim($add_notifications_query_text, ',');
        $add_notifications_query = $the_base->prepare($add_notifications_query_text);
        $add_notifications_result = $add_notifications_query -> execute();

        // mail($admin_mail, "--", count($notifications)." / ".$add_notifications_query_text);

        // on not inserting
        if($add_notifications_result != true){
            grain_status(
                "error",
                "",
                "notification hasn't been added: ".$add_notifications_query_text
            );
        }
    }
}






// testing old state
function get_user_state(){
    global $the_base, $user;
    $check_user_state_query_text = "SELECT `state` FROM `user_states` WHERE `user` = " . $user['id'] . " ORDER BY `id` DESC LIMIT 1;";
    $check_user_state_query = $the_base->prepare($check_user_state_query_text);
    $check_user_state_query->execute();
    $check_user_state_result = $check_user_state_query->fetchColumn();
    return $check_user_state_result;
}






// universal tasks getting
function get_tasks(
    $filters,    // user_id, date
    $sort,      // field, direction
    $offset = 1 // start
){
    /*
        WHAT FIELDS CAN a QUERY GET FILTERED BY?
        responsible
        status
        date_placed
        date_set
        date_term
    */

    global $the_base, $admin_mail;
    $limit_step = 5;

    // FILTER
    $filter_query = "";


    if(isset($filters['responsible']) or $filters['responsible'] != 0){
        $filter_query .= $filter_query == "" ? " WHERE " : " AND ";
        $filter_query .= " `responsible` = '".$filters['responsible']."' ";
    };
    if(isset($filters['status']) or $filters['status'] != 0){
        $filter_query .= $filter_query == "" ? " WHERE " : " AND ";
        $filter_query .= " `status` = '".$filters['status']."' ";
    };
    if(isset($filters['time_term_from'])){
        $filter_query .= $filter_query == "" ? " WHERE " : " AND ";
        $filter_query .= " DATE(`time_term`) >= DATE('".date_ltg($filters['time_term_from'])."') ";
    };
    if(isset($filters['time_term_to'])){
        $filter_query .= $filter_query == "" ? " WHERE " : " AND ";
        $filter_query .= " DATE(`time_term`) <= DATE('".date_ltg($filters['time_term_to'])."') ";
    };

    // only template
    $filter_query .= $filter_query == "" ? " WHERE " : " AND ";
    $filter_query .= " `template` = 0 ";


    // SORT
    /*
        $sort_query = "";
        if(isset($sort["by"]) and isset($sort["how"])){
            $sort_query = " ORDER BY `".$sort["by"]."` ".$sort["how"]." ";
        }
    */
    $sort_query = " ORDER BY `time_term` DESC ";


    // LIMIT
    $offset_query = " LIMIT ".($limit_step * ($offset-1)).", $limit_step ";


    $get_total_count_query_text = "SELECT count(*) FROM `tasks` ".$filter_query." ".$sort_query." ";
    $get_total_count_query = $the_base->prepare($get_total_count_query_text);
    $get_total_count_query->execute();
    $total_count = $get_total_count_query->fetchColumn();
    $pages = array(
        "count" => ceil($total_count / $limit_step),
        "current" => $offset
    );


    $get_tasks_query_text = "SELECT `id`, `title`, `time_term`, `responsible`, `status` FROM `tasks` ".$filter_query." ".$sort_query." ".$offset_query;
    $get_tasks_query = $the_base -> prepare($get_tasks_query_text);
    $get_tasks_query -> execute();




    if($get_tasks_query -> rowCount() > 0){
        $get_tasks_result = $get_tasks_query -> fetchAll(PDO::FETCH_ASSOC);

        // mail($admin_mail, "filter_query", "( ".$total_count." ) ".$get_tasks_query_text." - ".print_r($get_tasks_result,true));

        for($i=0; $i<count($get_tasks_result); $i++){
            $get_tasks_result[$i]['time_term'] = date_gtl($get_tasks_result[$i]['time_term'], true);
        }

        $get_task_list_output = array(
            "tasks" => $get_tasks_result,
            "pages" => $pages
        );

        grain_status(
            "ok",
            $get_task_list_output,
            "no tasks got filtered"
        );
    }else{
        grain_status(
            "surprise",
            array("surprise" => "no tasks"),
            "no tasks got filtered: ".$get_tasks_query_text
        );
    }
}