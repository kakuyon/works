<?php
session_start();

require_once("useful_tools.php");

// メッセージ初期化
$errorMessage = "";
$message = "";

// ユーザーの指定済か判定
$pointedUser = false;
// メールを送信したか判定
$sendMail = false;

// 決定ボタンが押された場合
if(isset($_POST["init"]) && !empty($_POST["init"])) {
  // CSRF対策
  if(isset($_POST["init_token"])) {
    if ($_POST["init_token"] !== $_SESSION["init_token"]) {
      $errorMessage .= "不正アクセスの可能性を検知<br>再度URLからアクセスしてください。<br>";
    }
  }

  // 値が入力されているか確認
  if(empty($_POST["user_id"])) {
    $errorMessage .= "ユーザー名が未入力です。<br>";
  }
  if(empty($_POST["address"])) {
    $errorMessage .= "メールアドレスが未入力です。<br>";
  }
    
  // エラーが存在しなければ続行
  if($errorMessage == "") {
    $userId = $_POST["user_id"];
    $address = $_POST["address"];

    try {
      // 入力情報に該当するユーザーが存在するか判定
      $pdo = new UserTable();
      $args = [":userId" => $userId, ":address" => $address];
      $stmt = $pdo->sendQuery("SELECT id, user_id, address, secret_question, locked, question_lock_count FROM users WHERE user_id = :userId AND address = :address AND registered = 1 AND removed = 0", $args);
      $rows = $stmt->fetchAll();

      // DBに存在するユーザーがいる場合
      if($rows) {
        $locked = $rows[0]["locked"];
        $userId = $rows[0]["user_id"];
        $address = $rows[0]["address"];

        // ロックを掛けられているユーザーだった場合
        if($locked == 1) { 
            $errorMessage .= "このアカウントはロックされています。<br>".
                            "解除するためのリンクをメールへ送信していますので、そちらで解除の手続きをお願いします。<br>"; 
        } else {
          // 入力情報に問題無し
          // 続けて秘密の質問に答えてもらう
          $pointedUser = true;
          $secQuestion = $rows[0]["secret_question"];
          $message .= "秘密の質問に答えてください。";
        }
      } else {
        // DBにユーザーが存在しない場合
        $errorMessage .= "指定されたユーザーは存在しません。<br>";
      }

    } catch(PDOException $e) {
      header("Content-Type: text/plain; charset=UTF-8", true, 500);
      exit($e->getMessage());
    }
  }
}

// シークレットフォームからデータ受信
if(isset($_POST["secret_submit"]) && !empty($_POST["secret_submit"])) {
  // CSRF対策
  if (!isset($_SESSION["secret_token"]) ||
      !isset($_POST["secret_token"]) ||
      empty($_POST["user_id"]) ||
      empty($_POST["address"]) ||
      $_POST["secret_token"] !== $_SESSION["secret_token"]) {
    $errorMessage .= "不正アクセスの可能性を検知<br>再度URLからアクセスしてください。<br>";
    // ユーザーの指定からやり直させる
    $pointedUser = false;
    $userId = "";
    $address = "";
  }

  if($errorMessage === "") {
    $pointedUser = true;
    $secQuestion = $_POST["secret_question"];

    // 値が入力されているか確認
    if(empty($_POST["sec_answer"])) {
      $errorMessage .= "秘密の質問の答えが未入力です。<br>";
    }

    // エラーが存在しなければ続行
    if($errorMessage == "") {
      $userId = $_POST["user_id"];
      $address = $_POST["address"];
      $secAnswer = $_POST["sec_answer"];
      // 対象ユーザーの情報を取得
      try {
        $pdo = new UserTable();
        $args = [":userId" => $userId, ":address" => $address];
        $stmt = $pdo->sendQuery("SELECT id, user_id, address, secret_answer, locked, question_lock_count FROM users WHERE user_id = :userId AND address = :address AND registered = 1 AND removed = 0", $args);
        $rows = $stmt->fetchAll();
        $locked = $rows[0]["locked"];
        $currentSecAnswer = $rows[0]["secret_answer"];

        // ロックを掛けられているユーザーの場合
        if($locked === 1) {
          $errorMessage .= "このアカウントはロックされています。<br>".
                           "解除するためのリンクをメールへ送信していますので、そちらで解除の手続きをお願いします。<br>";
          $pointedUser = false;
          $userId = "";
          $address = "";
        }

        // 秘密の質問の答えが一致している場合
        if($errorMessage === "" && 
           password_verify($secAnswer, $currentSecAnswer)) {
          // パスワード初期化メール送信準備
          $initToken = hash('sha256', uniqid(rand(), 1));
          $url = "http://localhost/login_system/init_process.php?init_token=$initToken";

          // メール処理
          $to = $address;
          $subject = "パスワード初期化用のURLを送信します";
          $headers = "From: postmaster@localhost";
$sendMessage = <<< EOM
{$userId}さんのパスワードを初期化するURLを送信しました。
パスワードを初期化する場合、24時間以内に以下のURLへ接続してください。

【パスワード初期化用URL】
{$url}

よろしくお願いします。
EOM;
                
          // メール送信
          mb_language("Japanese");
          mb_internal_encoding("UTF-8");
          if(mb_send_mail($to, $subject, $sendMessage, $headers)) {
            // 送信完了
            $_SESSION = array();
            $sendMail = true;
            $message .= "パスワード初期化手続きのメールを送信しました。<br>";

            $args = [":userId" => $userId, ":address" => $address, ":init_token" => $initToken];
            $pdo->sendQuery("UPDATE LOW_PRIORITY users SET init_date = now(), init_token = :init_token, question_lock_count = 0 WHERE user_id = :userId AND address = :address", $args);

          } else {
            // 送信失敗
            $errorMessage .= "メールの送信に失敗しました。<br>";
          }
        } elseif($errorMessage === "") {
          // 質問の答えが違う場合、間違えた回数をカウント
          $id = $rows[0]["id"];
          $args = [":id" => $id];
          $pdo->sendQuery("UPDATE LOW_PRIORITY users SET question_lock_count = question_lock_count + 1 WHERE id = :id", $args);
          $errorMessage .= "質問の答えが一致しませんでした。<br>";

          // 3回以上間違えていた場合はアカウントをロックする
          $lock_count = $rows[0]["question_lock_count"];
          if($lock_count >= 2) {
            $errorMessage .= "答えを3回以上間違えたためアカウントをロックします。<br>".
                             "解除するためのリンクをメールへ送信していますので、そちらで解除の手続きをお願いします。<br>";
            $resetToken = hash('sha256', uniqid(rand(), 1));
            $url = "http://localhost/login_system/unlock.php?reset_token=$resetToken";

            // メール処理
            $to = $address;
            $subject = "アカウントをロックしました";
            $headers = "From: postmaster@localhost";
$sendMessage = <<< EOM
現在、{$userId}さんのアカウントをロックしています。
ロックを解除するには、24時間以内に以下のURLへ接続する必要があります。

【ロック解除用URL】
{$url}

よろしくお願いします。
EOM;
            // メール送信
            mb_language("Japanese");
            mb_internal_encoding("UTF-8");
            if(mb_send_mail($to, $subject, $sendMessage, $headers)) {
              // 送信成功
              $args = [":id" => $id, ":reset_token" => $resetToken];
              $pdo->sendQuery("UPDATE LOW_PRIORITY users SET lock_date = now(), locked = 1, reset_token = :reset_token WHERE id = :id AND removed = 0", $args);
            } else {
              // 送信失敗
              $errorMessage .= "メールの送信に失敗しました。<br>";
            }
          } else {
            // 答えが間違っている＆間違えた回数が3回未満の場合
            $errorMessage .= "登録時に設定したものを入力してください。<br>";
          }
        }
      } catch(PDOException $e) {
        header("Content-Type: text/plain; charset=UTF-8", true, 500);
        exit($e->getMessage());
      }
    }
  }
}

?>

<?php $_SESSION["init_token"] = base64_encode(openssl_random_pseudo_bytes(32)); ?>
<?php $_SESSION["secret_token"] = base64_encode(openssl_random_pseudo_bytes(32)); ?>

<!DOCTYPE html>
  <head>
  	<meta charset='utf-8'>
  	<title>パスワード初期化手続き</title>
    <link rel="stylesheet" href="layout.css">
  </head>
  <body>
    <a href="login.php">ログイン画面へ</a>
    <h2>パスワードを初期化する</h2>
    <?php
      if($message != "") echo "<p>".$message."</p>";
    ?>
    <div style="display: <?= (!$pointedUser && !$sendMail) ? 'block' : 'none' ?>">
      <p>パスワードを初期化したいユーザー名とメールアドレスを記述してください。</p>
      <form class="input-form" action="" method="post" name="init_form">
        <div class="info-oneset">
          <label class="info-name" for="user_id">ユーザー名:</label>
          <input class="info-value" type="text" name="user_id" maxlength="128" required value="<?= isset($_POST['user_id']) ? $_POST['user_id'] : '' ?>">
        </div>
        <div class="info-oneset">
          <label class="info-name" for="address">メールアドレス:</label>
          <input class="info-value" type="email" id="address" name="address" maxlength="128" required value="<?= !empty($_POST['address']) ? $_POST['address'] : '' ?>">
        </div>

        <input type="hidden" name="init_token" value=<?= isset($_SESSION['init_token']) ? $_SESSION['init_token'] : NULL ?>>
        <?php
          if($errorMessage != "") echo "<p class='error'>".$errorMessage."</p>";
        ?>
        <p><a href="javascript:document.init_form.submit()">決定</a></p>
        <input type="hidden" name="init" value="init">
      </form>
    </div>
    <div style="display: <?= ($pointedUser && !$sendMail) ? 'block' : 'none' ?>">
      <form class="input-form" action="" method="post" name="secret_form">
        <div class="info-oneset">
          <label class="info-name" for="sec_answer"><?= $questions[$secQuestion] ?>:</label>
          <input class="info-value" type="text" name="sec_answer" maxlength="100" size="30" required value="<?= !empty($_POST['sec_answer']) ? $_POST['sec_answer'] : '' ?>">
        </div>
        <input type="hidden" name="secret_token" value=<?= isset($_SESSION['secret_token']) ? $_SESSION['secret_token'] : NULL ?>>
        <?php
          if($errorMessage != "") echo "<p class='error'>".$errorMessage."</p>";
        ?>
        <p><a href="javascript:document.secret_form.submit()">決定!</a></p>
        <input type="hidden" name="secret_submit" value="secret_submit">
        <input type="hidden" name="user_id" value="<?= $userId ?>">
        <input type="hidden" name="address" value="<?= $address ?>">
        <input type="hidden" name="secret_question" value="<?= $secQuestion ?>">
      </form>
    </div>
  </body>
</html>