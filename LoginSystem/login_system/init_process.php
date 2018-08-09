<?php
session_start();

require_once("useful_tools.php");

// メッセージ作成用
$errorMessage = "";
$message = "";
// 認証中か判定
$_SESSION["AUTHENTICATION"] = true;
// 確認メール経由でアクセスした可能性がある場合
if(!empty($_GET["init_token"])) {
  // トークンに値が入っていない場合
  if(!isset($_GET["init_token"])) {
    $errorMessage .= "もう一度やり直してください。";
  } else {
    // GETで渡されたトークンを取得
    $initToken = $_GET["init_token"];

    try {
      // DB内のトークンを取り出し、GETで渡されたトークンと比較する
      $pdo = new UserTable();
      $args = [":init_token" => $initToken];
      $stmt = $pdo->sendQuery("SELECT id FROM users WHERE init_token = :init_token AND locked = 0 AND init_date > now() - interval 24 hour AND removed = 0", $args);
      $rows = $stmt->fetchAll();
      if($rows) {  // データがあればメール経由でアクセスされてきたことを確定とする
        $id = $rows[0]["id"];

        if(isset($_POST["init_confirm"])) {
          // 変更後のパスワードが送信されてきた場合
          // CSRF対策
          if(!isset($_SESSION["init_confirm_token"]) ||
             $_POST["init_confirm_token"] != $_SESSION["init_confirm_token"]) {
            $errorMessage .= "不正アクセスの可能性を検知<br>再度URLからアクセスしてください。<br>";
          }
          
          // 値が入力されているか判定
          if(empty($_POST["password"])) {
            $errorMessage .= "パスワードが未入力です。<br>";
          }
          if(empty($_POST["confirm_password"])) {
            $errorMessage .= "パスワードを再入力してください。<br>";
          }

          // エラーが存在しなければ続行
          if($errorMessage == "") {
            $password = $_POST["password"];
            $confirmPassword = $_POST["confirm_password"];

            // パスワードの妥当性を確認
            if(!specifiedRange(mb_strlen($password), 8, 128)) {
              $errorMessage .= "パスワードの値が不正です。<br>".
                         "8~128文字にしてください。<br>";
            }
            // パスワード(確認)がパスワードと同じか確認
            if($confirmPassword !== $password) {
              $errorMessage .= "パスワードと確認用パスワードが一致しません。<br>";
            }
          }

          if($errorMessage == "") {
            // テーブルに登録する
            try {
              $password = password_hash($password, PASSWORD_DEFAULT);
              $pdo = new UserTable();
              $args = [":id" => $id,":password" => $password];
              $stmt = $pdo->sendQuery("UPDATE LOW_PRIORITY users SET password = :password, old_password = '', init_token = NULL WHERE id = :id", $args);

            } catch(PDOException $e) {
              header("Content-Type: text/plain; charset=UTF-8", true, 500);
              exit($e->getMessage());
            }
            $messageFromOutWindow = true;
            $message .= "更新しました。";
            $_SESSION["AUTHENTICATION"] = false;
          }
        } else {
          // POSTデータは送られていない
        }
      } else {
        // 該当するユーザーが存在しない
        $messageFromOutWindow = true;
        $errorMessage = "無効なトークンです。<br>";
      }

    } catch(PDOException $e) {
        header("Content-Type: text/plain; charset=UTF-8", true, 500);
        exit($e->getMessage());
    } 
  } 
}
?>

<?php $_SESSION["init_confirm_token"] = base64_encode(openssl_random_pseudo_bytes(32)); ?>

<!DOCTYPE html>
  <head>
    <meta charset='utf-8'>
    <title>パスワード初期化</title>
    <link rel="stylesheet" href="layout.css">
  </head>
  <body>
    <?php if($errorMessage != "" && isset($messageFromOutWindow)) echo "<p style=\"color: red\">".$errorMessage."</p>"; ?>
    <?php if($message != "" && isset($messageFromOutWindow)) echo "<p style=>".$message."</p>"; ?>
    <div style="display: <?= $_SESSION["AUTHENTICATION"] ? 'block' : 'none' ?>">
      <p>新しいパスワードを設定してください。</p>
      <form action="" method="post" name="init_form">
        <div class="info-oneset">
          <label class="info-name" for="password">パスワード: </label>
          <input class="info-value" type="password" name="password" maxlength="256" id="password" required onpaste="return false">
        </div>
        <div class="info-oneset">
          <label class="info-name" for="confirm_password">パスワード(確認): </label>
          <input class="info-value" type="password" id="confirm_password" name="confirm_password" maxlength="256" required onpaste="return false">
        </div>
        <input type="hidden" name="init_confirm_token" value="<?= $_SESSION['init_confirm_token']; ?>">
        <?php
          if($errorMessage != "" && !isset($messageFromOutWindow)) echo "<p class='error'>".$errorMessage."</p>";
          if($message != "") echo "<p>".$message."</p>";
        ?>
        <p><a href="javascript:document.init_form.submit()">パスワードを変更する</a></p>
        <input type="hidden" name="init_confirm" value="init_confirm">
      </form>
    </div>
    <a href="login.php">ログイン画面へ移動する</a>
  </body>
</html>