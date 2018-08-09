<?php
// セッション開始
session_start();

require_once("useful_tools.php");

// メッセージ作成用
$errorMessage = "";
$message = "";

// loginボタンが押された場合
if(isset($_POST["login"])) {
	// CSRF対策
  if($_POST["login_token"] != $_SESSION["login_token"]) {
    $errorMessage .= "不正アクセスの可能性を検知<br>再度URLからアクセスしてください。<br>";
  }

  // 値が入力されているか確認
  if(empty($_POST["user_id"])) {
    $errorMessage .= "ユーザー名が未入力です。<br>";
  }
  if(empty($_POST["address"])) {
    $errorMessage .= "メールアドレスが未入力です。<br>";
  }
  if(empty($_POST["password"])) {
    $errorMessage .= "パスワードが未入力です。<br>";
  }
  // エラーが存在しなければ続行
  if($errorMessage == "") {
  	$userId = $_POST["user_id"];
    $address = $_POST["address"];
  	$password = $_POST["password"];


  	// ユーザ名が以前に登録されたことのあるものか確認
    try {
      $pdo = new UserTable();
      $args = [":userId" => $userId, ":address" => $address];
      $stmt = $pdo->sendQuery("SELECT id, password, locked FROM users WHERE user_id = :userId AND address = :address AND registered = 1 AND removed = 0", $args);
      $rows = $stmt->fetchAll();

      // DBに該当するユーザーが存在する場合
      if($rows) {
        // ロックされているかの情報取得
      	$locked = $rows[0]["locked"];
      	if($locked == 1) { 
          // ロックされているユーザーの場合
      	  $errorMessage = "このアカウントはロックされています。<br>".
      	  							  "解除するためのリンクをメールへ送信していますので、そちらで解除の手続きをお願いします。<br>"; 
        } else {
	      	//DBからハッシュ化されたパスワードを取り出す
	      	$currentPassword = $rows[0]["password"];
					// パスワードを照合
	      	if(password_verify($password, $currentPassword)) {
		        // パスワードを間違えた回数リセット
		        $pdo->sendQuery("UPDATE LOW_PRIORITY users SET lock_count = 0 WHERE user_id = :userId AND address = :address", $args);
		        // ログイン
		        $userId = $rows[0]["id"];
				    $_SESSION["ID"] = $userId;
				    header("Location: index.php"); // メイン画面へ移行
				    exit;  // 処理終了
			  	} else {
			  		// パスワードが違っていた場合は間違えた回数としてカウントする
			  		$pdo->sendQuery("UPDATE LOW_PRIORITY users SET lock_count = lock_count + 1 WHERE user_id = :userId AND address = :address", $args);
			  		// 間違えた回数を取得する
			  		$stmt = $pdo->sendQuery("SELECT lock_count, user_id, address FROM users WHERE user_id = :userId AND address = :address", $args);
			  		$rows = $stmt->fetchAll();
			  		$lock_count = $rows[0]["lock_count"];
			  		$userId = $rows[0]["user_id"];
			  		$address = $rows[0]["address"];
			  		$errorMessage .= "ログインに失敗しました。<br>";

            // 3回以上間違えていた場合はロックを掛ける
			  		if($lock_count >= 3) {
			  			$errorMessage .= "パスワードを3回以上間違えたためアカウントをロックします。<br>".
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
								$pointedUser = true;
								$args = [":userId" => $userId, ":address" => $address, ":reset_token" => $resetToken];
							  $pdo->sendQuery("UPDATE LOW_PRIORITY users SET lock_date = now(), locked = 1, reset_token = :reset_token WHERE user_id = :userId AND address = :address AND removed = 0", $args);
							} else {
                // 送信失敗
								$errorMessage .= "メールの送信に失敗しました。<br>";
							}
						}
			  	}
		  	}

      } else {
        // 該当するユーザーが存在しない場合
      	$errorMessage .= "ログインに失敗しました。<br>";
      }

    } catch(PDOException $e) {
      header("Content-Type: text/plain; charset=UTF-8", true, 500);
      exit($e->getMessage());
    }
  }
}


// 確認メール経由でアクセスした可能性がある場合
if(!empty($_GET["regist_token"])) {
	// トークンに値が入っていない場合
	if(!isset($_GET["regist_token"])) {
		$errorMessage .= "もう一度登録をやり直してください。";
	} else {
		$registToken = $_GET["regist_token"];

		try {
			// DB内のトークンを取り出し、GETで渡されたトークンと比較する
    	$pdo = new UserTable();
    	$args = [":regist_token" => $registToken];
    	$stmt = $pdo->sendQuery("SELECT id FROM users WHERE regist_token = :regist_token AND registered = 0 AND date > now() - interval 24 hour", $args);
    	if($stmt->rowCount() == 1) {  // データがあればメール経由でアクセスされてきたことを確定とする
    		// 該当するユーザを取得
    		$rows = $stmt->fetchAll();

    		$userId = $rows[0]["id"];

    		// 本登録を完了させる
    		$args = [":id" => $userId];
    		$stmt = $pdo->sendQuery("UPDATE LOW_PRIORITY users SET registered = 1, regist_token = NULL WHERE id = :id", $args);
    		$registered = true;
    	} else {
        // トークンに該当するユーザーが存在しない
    		$errorMessage .= "無効なトークンです。";
    	}

    } catch(PDOException $e) {
    		header("Content-Type: text/plain; charset=UTF-8", true, 500);
    		exit($e->getMessage());
    }

    if($errorMessage == "") {
    	// ログイン
    $_SESSION["ID"] = $userId;
    header("Location: index.php"); // メイン画面へ移行
    exit;  // 処理終了
    }
	}
}
?>

<?php $_SESSION["login_token"] = base64_encode(openssl_random_pseudo_bytes(32)); ?>

<!DOCTYPE html>
  <head>
  	<meta charset='utf-8'>
  	<title>ログイン</title>
    <link rel="stylesheet" href="layout.css">
  </head>
  <body>
  	<h2>ログインシステム</h2>
  	<p>新規登録をするか、ログインをするか選択してください。</p>
  	
  	<form class="input-form" action="login.php" method="post" name="login_form">
  		<div class="info-oneset">
        <label class="info-name" for="user_id">ユーザー名:</label>
        <input class="info-value" type="text" id="user_id" name="user_id" maxlength="128" required value="<?= isset($_POST['user_id']) ? $_POST['user_id'] : "" ?>">
      </div>
      <div class="info-oneset">
        <label class="info-name" for="address">メールアドレス:</label>
        <input class="info-value" type="email" id="address" name="address" maxlength="128" required value="<?= isset($_POST['address']) ? $_POST['address'] : '' ?>">
      </div>
  		<div class="info-oneset">
        <label class="info-name" for="password">パスワード:</label>
        <input class="info-value" type="password" id="password" name="password" maxlength="128" required>
      </div>
  		<input type="hidden" name="login_token" value="<?= $_SESSION['login_token']; ?>">
      <?php
        if($errorMessage != "") echo "<p class='error'>".$errorMessage."</p>";
        if($message != "") echo "<p style=>".$message."</p>";
      ?>
  		<p><a class="command" href="javascript:document.login_form.submit()">ログイン</a>
         <a class="command" href="sign_up.php">新規登録</a></p>
      <input type="hidden" name="login" value="login">
  	</form>
  	<p><a href="init_pass.php">パスワードを忘れた場合</a></p>
  </body>
</html>