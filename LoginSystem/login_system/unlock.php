<?php
session_start();

require_once("useful_tools.php");

// メッセージ作成用
$errorMessage = "";
$message = "";

// 確認メール経由でアクセスした可能性がある場合
if(!empty($_GET["reset_token"])) {
  // トークンに値が入っていない場合
  if(!isset($_GET["reset_token"])) {
    $errorMessage .= "もう一度やり直してください。";
  } else {
    $resetToken = $_GET["reset_token"];

    try {
      // DB内のトークンを取り出し、GETで渡されたトークンと比較する
      $pdo = new UserTable();
      $args = [":reset_token" => $resetToken];
      $stmt = $pdo->sendQuery("SELECT id FROM users WHERE reset_token = :reset_token AND locked = 1 AND removed = 0", $args);
      if($stmt->rowCount() == 1) {  // データがあればメール経由でアクセスされてきたことを確定とする
        // 該当するユーザーを取得
        $rows = $stmt->fetchAll();
        $userId = $rows[0]["id"];
        // ロックを解除する
        $args = [":id" => $userId];
        $stmt = $pdo->sendQuery("UPDATE LOW_PRIORITY users SET locked = 0, lock_count = 0, question_lock_count = 0, reset_token = NULL WHERE id = :id", $args);
        $message = "ロックを解除しました。";
      } else {
        // 該当するユーザーが取得できない場合
        $errorMessage = "トークンが一致しませんでした。<br>";
      }

    } catch(PDOException $e) {
        header("Content-Type: text/plain; charset=UTF-8", true, 500);
        exit($e->getMessage());
    }
  }
} else {
  // その他の方法でアクセスされた場合
  header("Location: login.php"); // メイン画面へ移行
  exit;  // 処理終了
}
?>

<!DOCTYPE html>
  <head>
    <meta charset='utf-8'>
    <title>ロック解除</title>
    <link rel="stylesheet" href="layout.css">
  </head>
  <body>
    <?php
      if($errorMessage != "") echo "<p class='error'>".$errorMessage."</p>";
      if($message != "") echo "<p style=>".$message."</p>";
    ?>
    <p><a href="login.php">ログイン画面へ移動する</a></p>
  </body>
</html>