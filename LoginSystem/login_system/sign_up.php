<?php
	// セッション開始
	session_start();

  require_once("useful_tools.php");
  
  // エラーメッセージ用意
  $errorMessage = "";
  $message = "";

  // 仮登録が終了しているかの判定
  $exitSignUp = false;

  // registボタンが押された場合
  if(isset($_POST["regist"]) && !empty($_POST["regist"])) {
    // 値が入力されているか確認
    if(empty($_POST["user_id"])) {
      $errorMessage .= "ユーザーIDが未入力です。<br>";
    }
    if(empty($_POST["address"])) {
      $errorMessage .= "メールアドレスが未入力です。<br>";
    }
    if(empty($_POST["confirm_address"])) {
      $errorMessage .= "メールアドレスを再入力してください<br>";
    }
    if(empty($_POST["password"])) {
    	$errorMessage .= "パスワードが未入力です。<br>";
    }
    if(empty($_POST["confirm_password"])) {
    	$errorMessage .= "パスワードを再入力してください。<br>";
    }
    if(!isset($_POST["sec_question"])) {
      $errorMessage .= "秘密の質問が選択されていません。<br>";
    }
    if(empty($_POST["sec_answer"])) {
      $errorMessage .= "秘密の質問の答えが未入力です。<br>";
    }
    if(empty($_POST["confirm_sec_answer"])) {
      $errorMessage .= "秘密の質問の答えを再入力してください。<br>";
    }

    // エラーが存在しなければ続行
    if($errorMessage == "") {
      $userId = $_POST["user_id"];
      $address = $_POST["address"];
      $confirmAddress = $_POST["confirm_address"];
      $password = $_POST["password"];
      $confirmPassword = $_POST["confirm_password"];
      $secQuestion = $_POST["sec_question"];
      $secAnswer = $_POST["sec_answer"];
      $confirmSecAnswer = $_POST["confirm_sec_answer"];

      // ユーザー名の妥当性を確認
      if(!specifiedRange(mb_strlen($userId), 3, 16)) {
				$errorMessage .= "ユーザー名の値が不正です。<br>".
                         "3~16文字にしてください。<br>";
	  	}

      // メールアドレスの妥当性を確認
      if(!filter_var($address, FILTER_VALIDATE_EMAIL)) {
        $errorMessage .= "正しいメールアドレスを指定してください。<br>";
      }

      // メールアドレス(確認)がメールアドレスと同じか確認
      if($confirmAddress !== $address) {
        $errorMessage .= "メールアドレスが確認用の入力情報と一致しません。<br>";
      }

	  	// パスワードの妥当性を確認
	  	if(!specifiedRange(mb_strlen($password), 8, 128)) {
	  		$errorMessage .= "パスワードの値が不正です。<br>".
				                 "8~128文字にしてください。<br>";
	  	}
	  	// パスワード(確認)がパスワードと同じか確認
	  	if($confirmPassword !== $password) {
	  		$errorMessage .= "パスワードが確認用の入力情報と一致しません。<br>";
	  	}
      
      // 秘密の質問の妥当性を確認
      if(!ctype_digit($secQuestion) || $secQuestion > 6) {
        $errorMessage .= "秘密の質問を選択してください。<br>";
      }
      if(!specifiedRange(mb_strlen($secAnswer), 1, 50)) {
        $errorMessage .= "秘密の質問の答えは50文字以内にしてください。<br>";
      }
      // 秘密の質問の答えが秘密の質問の答え(確認)と同じか確認
      if($confirmSecAnswer !== $secAnswer) {
        $errorMessage .= "秘密の質問の答えが確認用の入力情報と一致しません。<br>";
      }

      // メールアドレス、ユーザー名が以前に登録されたことのあるものか確認
      try {
        $pdo = new UserTable();
        $args = [":user_id" => $userId,":address" => $address];
        $stmt = $pdo->sendQuery("SELECT user_id, address FROM users WHERE user_id = :user_id AND address = :address AND removed = 0", $args);
        $rows = $stmt->fetchAll();

        // 同じユーザーが存在している場合
        if($rows) $errorMessage .= "このユーザー名とメールアドレスの組み合わせは、既に利用されています。<br>"; 

      } catch(PDOException $e) {
        header("Content-Type: text/plain; charset=UTF-8", true, 500);
        exit($e->getMessage());
      }
    }

		// CSRF対策
   	if(!isset($_POST["signup_token"]) || !isset($_SESSION["signup_token"]) ||
       $_POST["signup_token"] != $_SESSION["signup_token"]) {
   		$errorMessage .= "不正アクセスの可能性を検知<br>再度URLからアクセスしてください。<br>";
    }

    // 全ての入力情報に問題が無い場合
    if($errorMessage == "") {
      $password = password_hash($password, PASSWORD_DEFAULT);
      $secAnswer = password_hash($secAnswer, PASSWORD_DEFAULT);
    	$registToken = hash('sha256', uniqid(rand(), 1));
    	$url = "http://localhost/login_system/login.php?regist_token=".$registToken;

    	// メール処理
			$to = $address;
			$subject = "本登録のお願い";
			$headers = "FROM: k.sakamoto@tbc-inc.co.jp";
$sendMessage = <<< EOM
現在、{$userId}さんは仮登録中です。
24時間以内に以下のURLに接続して、本登録を行ってください。

【本登録用URL】
{$url}

よろしくお願いします。
EOM;
			
			// メール送信
    	mb_language("Japanese");
			mb_internal_encoding("UTF-8");
			if(mb_send_mail($to, $subject, $sendMessage, $headers)) {
				// 送信成功
				$_SESSION = array();
        // テーブルに登録する
        try {
          $pdo = new UserTable();
          $args = [":user_id" => $userId, ":address" => $address, 
                   ":password" => $password, ":regist_token" => $registToken,
                   ":secret_question" => $secQuestion, ":secret_answer" => $secAnswer];
          $stmt = $pdo->sendQuery("INSERT INTO users (user_id, address, password, date, secret_question, secret_answer, regist_token) VALUES (:user_id, :address, :password, now(), :secret_question, :secret_answer, :regist_token)", $args);

        } catch(PDOException $e) {
          header("Content-Type: text/plain; charset=UTF-8", true, 500);
          exit($e->getMessage());
        }
        // メール送信も問題なく終了
				$exitSignUp = true;
			} else {
        // 送信失敗
				$errorMessage .= "メールの送信に失敗しました。<br>";
			}
    } 
  }
?>


<?php $_SESSION["signup_token"] = base64_encode(openssl_random_pseudo_bytes(32)); ?>

<!DOCTYPE html>
  <head>
  	<meta charset='utf-8'>
  	<title>login</title>
    <link rel="stylesheet" href="layout.css">
  </head>
  <body>
    <a href="login.php">ログイン画面へ</a>
  	<h2>新規登録</h2>
    
    <?php 
      if($exitSignUp) echo "仮登録が完了しました。<br>メールを送信しましたので、確認してください。<br>";
    ?>
    <div style="display: <?= (!$exitSignUp) ? 'block' : 'none' ?>">
      <p>以下のフォームより、新規登録の手続きをお願いします。<br>
        <span class="alert">
          ※ユーザー名は3文字から16文字にしてください。<br>
          ※パスワードは8文字から128文字にしてください。<br>
          ※秘密の質問の答えは50文字以内にしてください。<br>
          ※秘密の質問はパスワードを初期化する時に確認するものです。<br>
          　一度登録すると変更できません。<br>
        </span>
      </p>
    	<form class="input-form" action="sign_up.php?" method="post" name="regist_form">
    		<div class="info-oneset">
          <label class="info-name" for="user_id">ユーザー名:</label>
          <input class="info-value" type="text" id="user_id" name="user_id" maxlength="32" required value=<?= !empty($_POST['user_id']) ? $_POST['user_id'] : '' ?> >
        </div>

    		<div class="info-oneset">
          <label class="info-name" for="address">メールアドレス:</label>
          <input class="info-value" type="email" id="address" name="address" maxlength="128" required onpaste="return false">
        </div>
        <div class="info-oneset">
          <label class="info-name" for="confirm_address">メールアドレス(確認):</label>
          <input class="info-value" type="email" id="confirm_address" name="confirm_address" maxlength="128" require onpaste="return false">
        </div>

    		<div class="info-oneset">
          <label class="info-name" for="password">パスワード:</label>
          <input class="info-value" type="password" id="password" name="password" maxlength="256" onpaste="return false" required>
        </div>
    		<div class="info-oneset">
          <label class="info-name" for="confirm_password">パスワード(確認):</label>
          <input class="info-value" type="password" id="confirm_password" name="confirm_password" maxlength="256" onpaste="return false" required>
        </div>

        <hr>
        <p>秘密の質問</p>

        <div class="info-oneset">
          <label class="info-name" for="sec_question">質問内容:</label>
          <select class="info-value" id="sec_question" name="sec_question" size="1">
            <?php
              $i = 0;
              $select = isset($_POST["sec_question"]) ? $_POST["sec_question"] : 0;
              foreach($questions as $question) {
                echo ($i == $select) ?
                "<option value='${i}' selected>${question}</option>" : 
                "<option value='${i}'>${question}</option>";
                $i++;
              }
            ?>
          </select>
        </div>

        <div class="info-oneset">
          <label class="info-name" for="sec_answer">質問の答え:</label>
          <input class="info-value" type="text" id="sec_answer" name="sec_answer" maxlength="100" required onpaste="return false">
        </div>
        <div class="info-oneset">
          <label class="info-name" for="confirm_sec_answer">質問の答え(確認):</label>
          <input class="info-value" type="text" id="confirm_sec_answer" name="confirm_sec_answer" maxlength="100" required onpaste="return false">
        </div>

    		<input type="hidden" name="signup_token" value="<?= $_SESSION['signup_token']; ?>">
        <?php
          if($errorMessage != "") echo "<p class='error'>".$errorMessage."</p>";
          if($message != "") echo "<p style=>".$message."</p>";
        ?>
    		<p><a href="javascript:document.regist_form.submit()">登録</a></p>
        <input type="hidden" name="regist" value="regist">
    	</form>
    </div>
  </body>
</html>