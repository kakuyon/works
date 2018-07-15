<?php
session_start();

require_once("useful_tools.php");

// ログインしていない場合はログインフォームへ
checkLogin("redirect");

// ログインユーザの登録情報を取得
try {
  $pdo = new UserTable();
  $args = [":id" => $_SESSION["ID"]];
  $stmt = $pdo->sendQuery("SELECT user_id, address FROM users WHERE id = :id AND removed = 0", $args);
  $rows = $stmt->fetchAll();
  if($rows) {
    $userId = $rows[0]["user_id"];
    $address = $rows[0]["address"];
  }

} catch(PDOException $e) {
  header("Content-Type: text/plain; charset=UTF-8", true, 500);
  exit($e->getMessage());
}


?>

<!DOCTYPE html>
  <head>
  	<meta charset='utf-8'>
  	<title>Main</title>
    <link rel="stylesheet" href="layout.css">
  </head>
  <body>
  	<h2>メイン</h2>
    <p>ようこそ<?= hs($userId) ?>さん</p>
    <ul class="main">
      <li class="main-oneset">
        <span class="main-name">ユーザー名:</span> <?= hs($userId) ?>
      </li>
      <li class="main-oneset">
        <span class="main-name">メールアドレス:</span> <?= hs($address) ?>
      </li>
    </ul>
    <p><a class="command" href="edit_info.php">編集する</a></p>
    <p><a class="command" href="logout.php">ログアウト</a></p>
    <p><a class="command" href="quit.php">退会する</a></p>
  </body>
</html>