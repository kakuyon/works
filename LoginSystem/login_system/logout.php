<?php
session_start();

require_once("useful_tools.php");

// ログインしていない場合はログインフォームへ
checkLogin("redirect");  

// メッセージ作成用
$message = "ログアウトしました。";
$errorMessage = "";

// セッション初期化
$_SESSION = array();
session_destroy();
?>

<!DOCTYPE html>
  <head>
  	<meta charset='utf-8'>
  	<title>ログアウト</title>
    <link rel="stylesheet" href="layout.css">
  </head>
  <body>
    <?php
      if($errorMessage != "") echo "<p class='error'>".$errorMessage."</p>";
      if($message != "") echo "<p>".$message."</p>";
    ?>
    <p><a href="login.php">ログイン画面へ移動する</a></p>
  </body>
</html>