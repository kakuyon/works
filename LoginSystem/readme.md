# ログインシステム

## 使用言語
HTML,CSS, PHP

## 説明
素のPHPで、アカウント登録の可能なシステムを構築してみました。

** 注意 **
このシステムはXAMPPを利用しています。
閲覧するには、XAMPPをインストールし、
* login_systemをxampp/htdocs/に入れる
* XAMPPを起動し、mysqlコマンドでdump.sqlをリストアする
という作業を行う必要があります。

また、データベースに関しては
* データベース名
  * "login_system"
* ホスト名
  * "127.0.0.1"
* ユーザー名
  * "root"
* パスワード
  * ""
* 文字コード
  * "utf8"
以上の値が設定されています。
変更するには、login_system/useful_tools.php内のUserTableクラスにある定数を弄る必要があります。