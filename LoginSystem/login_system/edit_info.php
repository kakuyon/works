<?php
session_start();

require_once("useful_tools.php");

// メッセージ用意
$message = "";
$errorMessage = "";

// 編集が終了しているかの判定用
$exitEdit = false;
$failedEdit = false;

// 編集用の変数初期化
$userId = "";
$address = "";
$confirmAddress = "";
$changeAddress = false;
$changePassword = false;
$pdo = new UserTable();

// ログイン済みかチェック
if(!checkLogin()) {
  $errorMessage = "ログインし直してください。<br>";
}

// loginボタンが押された場合
if($errorMessage === "" &&
   isset($_POST["update"]) && !empty($_POST["update"])) {
  // CSRF対策
  if($_POST["update_token"] != $_SESSION["update_token"]) {
    $errorMessage .= "不正アクセスの可能性を検知<br>再度URLからアクセスしてください。<br>";
  }

  $rows = $pdo->getInfo($_SESSION["ID"]);
  // 値が入力されているか確認  
  // ユーザー名
  if(empty($_POST["user_id"])) { 
    $userId = $rows[0]["user_id"];
  } else {
    $userId = $_POST["user_id"];
  }
  // パスワード
  if(empty($_POST["password"])) {
    $password = "";
  } else {
    $password = $_POST["password"];
    $changePassword = true; 
  }
  // パスワード(確認)
  if(empty($_POST["confirm_password"]) && $changePassword) {
    $errorMessage .= "パスワードを再入力してください。<br>";
  } else { 
    $confirmPassword = $_POST["confirm_password"];
  }
  // メールアドレス
  if(empty($_POST["address"])) {
    $address = $rows[0]["address"];
  } else {
    $changeAddress = ($_POST["address"] !== $rows[0]["address"]) ? true : false;
    $address = $_POST["address"];
  }
  // メールアドレス(確認)
  if(empty($_POST["confirm_address"]) && !empty($_POST["address"])) {
    $errorMessage .= "メールアドレスを再入力してください。<br>";
  } else {
    $confirmAddress = $_POST["confirm_address"];
  }

  // エラーが存在しなければ続行
  if($errorMessage == "") {
    // ユーザー名の妥当性を確認
    if(!specifiedRange(mb_strlen($userId), 3, 16)) {
      $errorMessage .= "ユーザー名の値が不正です。<br>".
                       "3~16文字にしてください。<br>";
    }
    // メールアドレスの妥当性を確認
    if($changeAddress && !filter_var($address, FILTER_VALIDATE_EMAIL)) {
      $errorMessage .= "正しいメールアドレスを指定してください。<br>";
    }
    // メールアドレス(確認)がメールアドレスと同じか確認
    if($address !== $confirmAddress &&
       !empty($_POST["confirm_address"]) && !empty($_POST["address"])) {
      $errorMessage .= "メールアドレスと確認用メールアドレスが一致しません。<br>";
    }
    // パスワードの妥当性を確認
    if($changePassword && !specifiedRange(mb_strlen($password), 8, 128)) {
        $errorMessage .= "パスワードの値が不正です。<br>".
                         "8~128文字にしてください。<br>";
    }
    // パスワード(確認)がパスワードと同じか確認
    if($changePassword && $password !== $confirmPassword) {
      $errorMessage .= "パスワードと確認用パスワードが一致しません。<br>";
    }
    // DB関係の確認
    try {
      // メールアドレス、ユーザーIDが以前に登録されたことのあるものか確認
      $args = [":id" => $_SESSION["ID"], ":user_id" => $userId, ":address" => $address];
      $stmt = $pdo->sendQuery("SELECT id, user_id FROM users WHERE id != :id AND user_id = :user_id AND address = :address AND removed = 0", $args);
      $rows = $stmt->fetchAll();
      if($rows) $errorMessage .= "このユーザー名とメールアドレスの組み合わせは既に登録されています。<br>";
      
      // パスワードが以前に使われていたものか確認
      $rows = $pdo->getInfo($_SESSION["ID"]);
      $id = $_SESSION["ID"];
      $currentUserId = $rows[0]["user_id"];
      $currentPassword = $rows[0]["password"];
      $oldPassword = $rows[0]["old_password"];
      if(password_verify($password, $oldPassword) || password_verify($password, $currentPassword)) {
        $errorMessage .= "直近2回で利用していたパスワードは使用できません。<br>";
      }

    } catch(PDOException $e) {
      header("Content-Type: text/plain; charset=UTF-8", true, 500);
      exit($e->getMessage());
    }
  }
  
  // 全ての入力情報に問題が無い場合
  if($errorMessage == "") {
    try {
      // 登録情報更新
      if($changePassword) {
        $args = [":id" => $id, ":password" => password_hash($password, PASSWORD_DEFAULT)];
        $pdo->sendQuery("UPDATE LOW_PRIORITY users SET old_password = password, password = :password WHERE id = :id", $args);
        $message .= "パスワードを更新しました。<br>";
      }

      // メールアドレスの変更を行っていた場合
      if($changeAddress) {
        $changeToken = hash('sha256', uniqid(rand(), 1));
        $url = "http://localhost/login_system/edit_info.php?change_token=".$changeToken;

        // メール処理
        $to = $address;
        $subject = "メールアドレスによる認証のお願い";
        $headers = "From: postmaster@localhost";
$sendMessage = <<< EOM
{$currentUserId}さんは登録情報の再設定を行いました。
設定された登録情報を反映させるためには、
24時間以内に以下のURLへ接続する必要があります。

【更新用URL】
{$url}

よろしくお願いします。
EOM;
        // メール送信
        mb_language("Japanese");
        mb_internal_encoding("UTF-8");
        if(mb_send_mail($to, $subject, $sendMessage, $headers)) {
          // 送信成功
          $id = $_SESSION["ID"];
          $_SESSION = array();
          $_SESSION["ID"] = $id;
          $message .= "新たに設定したアドレスへ認証メールを送信しました。<br>";
        }

        $args = [":id" => $id, ":user_id" => $userId, ":address" => $address, ":change_token" => $changeToken];
        $pdo->sendQuery("UPDATE LOW_PRIORITY users SET change_user_id = :user_id, change_address = :address, change_date = now(), change_token = :change_token, changed = 1 WHERE id = :id", $args);
      } else {
        // メールアドレスによる認証が必要ない場合、認証せずにユーザー名を変更する
        $args = [":id" => $id, ":user_id" => $userId];
        $pdo->sendQuery("UPDATE LOW_PRIORITY users SET user_id = :user_id WHERE id = :id", $args);
        $message .= "ユーザー名を更新しました。<br>";
      }
      // 正常終了
      $exitEdit = true;
    } catch(PDOException $e) {
      header("Content-Type: text/plain; charset=UTF-8", true, 500);
      exit($e->getMessage());
    }
  }
  // 更新ができていない場合
  if(!$exitEdit) $failedEdit = true;

} elseif($errorMessage === "" &&
         !empty($_GET["change_token"])) {
  // 確認メール経由でアクセスした可能性がある場合
  if(!isset($_GET["change_token"])) {
    // トークンに値が入っていない場合
    $errorMessage .= "もう一度登録をやり直してください。";
  } else {
    $changeToken = $_GET["change_token"];

    try {
      // DB内のトークンを取り出し、GETで渡されたトークンと比較する
      $pdo = new UserTable();
      $args = [":change_token" => $changeToken];
      $stmt = $pdo->sendQuery("SELECT id, user_id, address FROM users WHERE change_token = :change_token AND locked = 0 AND changed = 1 AND change_date > now() - interval 24 hour AND removed = 0", $args);
      if($stmt->rowCount() == 1) {  // データがあればメール経由でアクセスされてきたことを確定とする
        // 該当するユーザを取得
        $rows = $stmt->fetchAll();
        $id = $rows[0]["id"];
        $userId = $rows[0]["user_id"];
        $address = $rows[0]["address"];

        // 登録情報を更新させる
        $args = [":id" => $id];
        $pdo->sendQuery("UPDATE LOW_PRIORITY users SET user_id = change_user_id, address = change_address, change_token = NULL, changed = 0 WHERE id = :id", $args);
        
      } else {
        // 該当するユーザーがいない場合
        $errorMessage .= "無効なトークンです。";
      }

    } catch(PDOException $e) {
        header("Content-Type: text/plain; charset=UTF-8", true, 500);
        exit($e->getMessage());
    }

    if($errorMessage == "") {
      // ログイン
      $_SESSION["ID"] = $id;
      $message .= "ユーザー名とメールアドレスの更新が完了しました。<br>";
    }
  }
} elseif($errorMessage === "") {
  // 編集画面へ移行したばかりの時の処理
  try {
    // 現在の登録情報を取得
    $args = [":id" => $_SESSION["ID"]];
    $stmt = $pdo->sendQuery("SELECT user_id, address FROM users WHERE id = :id", $args);
    $rows = $stmt->fetchAll();
    if($rows) {
       $userId = $rows[0]["user_id"];
       $address = $rows[0]["address"];
       $confirmAddress = $address;
    }

  } catch(PDOException $e) {
      header("Content-Type: text/plain; charset=UTF-8", true, 500);
      exit($e->getMessage());
  }
}

?>

<?php $_SESSION["update_token"] = base64_encode(openssl_random_pseudo_bytes(32)); ?>

<!DOCTYPE html>
  <head>
    <meta charset='utf-8'>
    <title>Main</title>
    <link rel="stylesheet" href="layout.css">
  </head>
  <body>
    <p><a href="index.php">メイン画面へ戻る</a></p>
    <h2>登録情報の編集</h2>
    <?php
        if($message != "") echo "<p style=>".$message."</p>";
      ?>
    <div style="display: <?= (!$exitEdit) ? 'block' : 'none' ?>">
      <p>
        登録情報を編集し終えたら、更新ボタンを押してください。<br>
        <span class="alert">
          ※未入力の箇所は現在の登録情報が適用されます。<br>
          ※メールアドレスを変更する際は、ユーザー名とメールアドレスの認証を行います。
        </span>
      </p>
      <form class="input-form" action="" method="post" name="edit_form">
        <div class="info-oneset">
          <label class="info-name" for="user_id">ユーザー名: </label>
          <input class="info-value" type="text" id="user_id" name="user_id" maxlength="32" placeholder="<?= $failedEdit && $_POST['user_id'] != '' ? '' : hs($userId) ?>" value="<?= $failedEdit && $_POST['user_id'] != '' ? hs($userId) : '' ?>">
        </div>

        <div class="info-oneset">
          <label class="info-name" for="address">メールアドレス: </label>
          <input class="info-value" type="email" id="address" name="address" maxlength="128" onpaste="return false" placeholder="<?= $failedEdit && $_POST['address'] != '' ? '' : hs($address) ?>">
        </div>
        <div class="info-oneset">
          <label class="info-name" for="confirm_address">メールアドレス(確認): </label>
          <input class="info-value" type="email" id="confirm_address" name="confirm_address" maxlength="128" onpaste="return false" placeholder="<?= $failedEdit && $_POST['address'] != '' ? '' : hs($address) ?>">
        </div>

        <div class="info-oneset">
          <label class="info-name" for="password">パスワード: </label>
          <input class="info-value" type="password" id="password" name="password" maxlength="256" onpaste="return false">
        </div>
        <div class="info-oneset">
          <label class="info-name" for="confirm_password">パスワード(確認): </label>
          <input class="info-value" type="password" id="confirm_password" name="confirm_password" maxlength="256" onpaste="return false">
        </div>

      <input type="hidden" name="update_token" value="<?= $_SESSION['update_token'] ?>">
      <?php
        if($errorMessage != "") echo "<p class='error'>".$errorMessage."</p>";
      ?>
      <p><a href="javascript:document.edit_form.submit()">更新する</a></p>
      <input type="hidden" name="update" value="update">
      </form>
    </div>
  </body>
</html>