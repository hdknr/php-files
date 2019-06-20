<?php
function login_required($ctx){
    if (isset($_SESSION['auth']) && $_SESSION['auth'])  {
        return;
    }
    header("Location: {$ctx->login_url}?file={$ctx->request_path}", true, 301);
    exit;
}

function authenticate($ctx) {
    if (!isset($_POST['password'])) {
        return null;
    }
    $path = $ctx->request_file; 
    $pwd = $_POST['password'];

    $result = array_filter($ctx->passwords, function($item) use (&$pwd, &$path) {
        if(count($item) > 2 ){
           return (strpos($item[0], $path) !== false) && (strpos($item[1], $pwd) !== false);
        }
        return false;
    });

    $var = current($result);
    if ($var === false) {
        return null;
    }

    return array('url' => $var[0], 'redir' => $var[2]);
}


function download($abspathname) {
    $filename = basename($abspathname);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($abspathname)); //Absolute URL
    
    ob_clean();
    flush();
    readfile($abspathname);
}

function http404($ctx) {
    http_response_code(404);
    include $ctx->template_404;
}

function render_html($abspath){
    include $abspath;
}

function getTargets($data_abspathname) {
    $path = $data_abspathname ;
    $files = array(
        $path, "{$path}/index.html"
    );
    return $files;
}

function process($ctx) {
    $files = getTargets($ctx->data_abspathname);

    foreach($files as $file) {
        if (is_file($file)) {
            if (preg_match('/\.html$/', $file)) {
                render_html($file);
                return;
            }
            download($file);
            session_destroy();
            return;
        }
    }
    http404($ctx);
}

function init() {
    session_start();

    $login_url = '/files/login.html';
    $thanks_url = '/files/thanks.html';

    $request_uri = parse_url($_SERVER['REQUEST_URI']);
    $request_path = $request_uri['path'];
    $request_file = isset($_GET['file']) ? $_GET['file'] : '';
    $script_name = $_SERVER['SCRIPT_NAME'];
    $document_root = $_SERVER['DOCUMENT_ROOT'];
    $base = dirname($script_name);
    $app_dir = dirname(dirname("{$document_root}{$base}"));
    $data_dir = "{$app_dir}/data";
    $template_dir = "{$app_dir}/templates";
    $password_file = "{$app_dir}/.password";
    $passwords = array_map('str_getcsv',file($password_file));
    $data_pathname = str_replace($base, '', $request_path);
    $data_abspathname = "{$data_dir}{$data_pathname}";

    $template_login = "{$template_dir}/login.html";
    $template_thanks = "{$template_dir}/thanks.html";
    $template_404 = "{$template_dir}/404.html";

    return (object)get_defined_vars();
}

function info_controller($ctx) {
    login_required($ctx);

    header("Access-Control-Allow-Origin: *");
    $info = $_SESSION['info'];
    echo json_encode($info); 
}

function login_controller($ctx) {
    $cred = authenticate($ctx);

    if($cred !== null) { 
        $_SESSION['auth'] = true;
        $_SESSION['info'] = $cred;
        $location = "Location: {$ctx->thanks_url}";
        header($location, true, 301); 
        return;
    }

    include $ctx->template_login;
}

function data_controller($ctx) {
    login_required($ctx);
    process($ctx);
}

function thanks_controller($ctx) {
    login_required($ctx);
    include $ctx->template_thanks;
}

function dispatch() {
    $ctx = init();

    $routes = array(
        '/^\/files\/login.html$/' => function() use(&$ctx){login_controller($ctx);},
        '/^\/files\/thanks.html$/' => function() use(&$ctx){thanks_controller($ctx);},
        '/^\/files\/info.json/' => function() use(&$ctx){info_controller($ctx);},
        '/^\/files.+/' => function() use(&$ctx){data_controller($ctx);});
        
    foreach($routes as $pattern => $func) {
        if(preg_match($pattern, $ctx->request_path)){
            return $func();
        }
    }
}

dispatch();
?>
