<?php
session_start();

require_once("useful_tools.php");

// ログインしていない場合はリダイレクト
checkLogin("redirect");

require_once("useful_tools.php");

// エラーメッセージ初期化
$errorMessage = "";
$message = "";

// ログインしている場合
if(isset($_POST["confirm_quit"]) && !empty($_POST["confirm_quit"])) {
  // CSRF対策
  if(!empty($_POST["quit_token"]) && $_POST["quit_token"] != $_SESSION["quit_token"]) {
    $errorMessage .= "不正アクセスの可能性を検知<br>再度URLからアクセスしてください。<br>";
  }
  
  if($errorMessage === "") {
    // ユーザを消去
    $pdo = new UserTable();
    $args = [":id" => $_SESSION["ID"]];
    $pdo->sendQuery("UPDATE LOW_PRIORITY users SET removed = 1 WHERE id = :id", $args);
    $message = "退会しました。<br>";
    $_SESSION = array();
    session_destroy();
    $exitQuit = true;
  }

} else {
  $message = "<b>本当に退会しますか？</b>";
}

?>

<?php $_SESSION["quit_token"] = base64_encode(openssl_random_pseudo_bytes(32)); ?>

<!DOCTYPE html>
  <head>
  	<meta charset='utf-8'>
  	<title>退会</title>
    <link rel="stylesheet" href="layout.css">
  </head>
  <body>
    <?php
      if($errorMessage != "") echo "<p class='error'>".$errorMessage."</p>";
      if($message != "") echo "<p>".$message."</p>";
    ?>
    <p><a href="index.php">メイン画面へ戻る</a></p>
    <div style="display: <?= (!isset($exitQuit)) ? 'block' : 'none' ?>">
      <form action="quit.php" method="post" name="quit_form">
        <input type="hidden" name="quit_token" value="<?= $_SESSION['quit_token']; ?>">
        <?php
          if(!isset($exitQuit)) {
            echo "<a href='javascript:document.quit_form.submit()'>退会する</a>";
            echo "<input type='hidden' name='confirm_quit' value='confirm_quit'>";
          }
        ?>
      </form>
    </div>
  </body>
</html>