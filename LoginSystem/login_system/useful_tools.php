<?php
  // 秘密の質問の内容
  $questions = [
    "好きな食べ物は？",
    "嫌いな食べ物は？",
    "好きな本の題名は？",
    "好きな言語は？",
    "初めて飛行機で行った場所は？",
    "子供時代に住んでいた町の名前は？",
  ];

  // データベースに関するクラス
  class UserTable {
    const DB = "login_system";  // データベース名
    const HOST = "127.0.0.1";  // ホスト名
    const USERNAME = "root";   // ユーザー名
    const PASSWORD = "";       // パスワード
    const CHARSET = "utf8";    // 文字コード

    // サーバへ接続したインスタンスを返す
    private function getConnection() {
      $db = self::DB;
      $host = self::HOST;
      $username = self::USERNAME;
      $password = self::PASSWORD;
      $charset = self::CHARSET;
      $dsn = "mysql:dbname=$db;host=$host;charset=$charset";

      $connection = new PDO($dsn, $username, $password,
                    [
                      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // SQLで起こったエラーをスローする
                      PDO::ATTR_EMULATE_PREPARES => false,       // 複数のSQLを同時に実行できるようにはしない(パフォーマンス向上)
                    ]
                    );
      return $connection;
    }

    // ユーザーの情報を取得
    function getInfo($id) {
      $args = [":id" => $id];
      $stmt = $this->sendQuery("SELECT * FROM users WHERE id = :id", $args);
      return $stmt->fetchAll();
    }

    // 与えられたクエリの送受信を行う
    function sendQuery($sql, $args) {
      $connection = $this->getConnection();
      $stmt = $connection->prepare($sql);
      $stmt->execute($args);
      return $stmt;
    }

  }

  // $valueが$minから$maxの範囲内に存在するか判定
  function specifiedRange($value, $min, $max) {
    return ($min <= $value && $value <= $max) ? true : false;
  }

  // html用にエスケープ処理をする
  function hs($value) {
    return htmlspecialchars($value, ENT_QUOTES);
  }
  
  // ログイン済みか判定する
  function checkLogin($option = "none") {
    if(isset($_SESSION["ID"])) {
      return true;
    } else {
      // $optionの設定次第でリダイレクト
      if($option === "redirect") {
        header("Location: login.php");
        exit;
      }
      return false;
    }
  }
