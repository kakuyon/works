# -*- coding: utf-8 -*-
import pygame
import glob
import random
import time
import os.path
import sys


# テキスト内の「select_name」に関して記載している行から
# 値を取得する関数
def getvalue (text, select_name) :
	word = ""
	try :
		for line in text :
			# 行の先頭が改行かコメントを示していた場合は次の行へ
			if line[0] == "#" or line == "\n" :
				continue

			data = line.split()

			# select_name指定してある項目に関する行か識別
			if data[0] == select_name :
				# 「=」の右側にある文字列を取得
				if data[1] == "=" :
					word = data[2]
				elif data[1].count("=") :
					word = data[1].rsplit("=", 1)[1]
			# select_nameと「=」が連結されていて
			# 拾いきれなかった可能性もチェック
			elif data[0].count(select_name) and \
				 data[0].count("=") :
				# 「=」の右側にある文字列を取得
				if len(data) == 1 :
					word = data[0].rsplit("=", 1)[1]
				else :
					word = data[1]
	# エラー処理
	except Exception as e :
		print("テキストの取得に失敗しました。")
		print("ErrorMessage: ")
		print(e)
	return word

# ディレクトリ内の音楽ファイルの再生順序を変更する関数
def shuffle (playlist) :
	random.shuffle(playlist)

# 再生等に関する制御(未実装)
def pushswbutton (playlist) :
	if swnm == "play" :
		play(playlist)
	elif swnm == "restart" :
		restart()
	else :
		pause()


def play (playdir) :
	try :
		pygame.mixer.music.load(playdir)
		pygame.mixer.music.set_volume(vol)
		pygame.mixer.music.play()

		print(playdir + "の読み込み完了")

	except Exception as e :
		print("ファイルの読み込みに失敗しました。")
		print("ErrorMessage: ")
		print(e)

# 音楽一時停止(未実装)
def pause () :
	pygame.mixer.music.pause()
	swnm = "restart"

# 音楽再生再開(未実装)
def restart () :
	pygame.mixer.music.unpause()
	swnm = "pause"


##########ここからメイン##########
# 再生に関する設定ファイルを開く
try :
	opentext = open("System.txt", "r", encoding="utf-8")
except Exception as e :
	print("テキストを開くのに失敗しました。")
	print("ErrorMessage: ")
	print(e)

# 再生する音楽ファイルが入っているディレクトリを調べる
play_dir = getvalue(opentext, "work_directory_location")
opentext.close()
	
# ディレクトリの末尾に「\」を追加
if play_dir[-1:] != "\\" :
	play_dir = play_dir + "\\"
# ディレクトリが存在しない場合は終了
if not os.path.exists(play_dir) :
	print("work_directory_locationに設定されてあるディレクトリは存在しません。(System.txt参照)")
	exit()

# その他、各設定内容を調べる
try :
	# 音量に関する入力情報取得
	opentext = open("System.txt", "r", encoding="utf-8")
	vol = float(getvalue(opentext, "volume"))
	opentext.close()

	# 音楽再生方法に関する入力情報取得
	opentext = open("System.txt", "r", encoding="utf-8")
	playswi = int(getvalue(opentext, "player_switch"))
	opentext.close()

	# 音楽再生の順番に関する入力情報取得
	opentext = open("System.txt", "r", encoding="utf-8")
	shuffleswi = int(getvalue(opentext, "always_shuffle"))
	opentext.close()

# エラー処理
except Exception as e :
	print("System.txtの内容を正常に取得できませんでした。")
	print("ErrorMessage:")
	print(e)

# 変数等初期化
pygame.mixer.init()
path = glob.glob(play_dir + "*")
playlist = []
playnum = 0
shufflemode = 0
now = "start"	# 現在の状況に関して記録

# 再生できるファイルのみを取得
for filename in path :
	extension = filename[-3 :]
	if extension == "wav" or extension == "ogg" or extension == "mp3" :
		playlist.append(filename)

# 音楽ファイルが存在しない場合は終了
if playlist == []:
	print(play_dir + "内に再生できるファイルが存在しません。")
	exit()

print("-Sound Roll-")
# 起動直後に再生を行うか確認
if playswi == 0 :
	path
else :
	if playswi == 2:
		shufflemode = 1
		shuffle(playlist)
	now = "play"

while True :
	# 起動直後の場合
	if now == "start" :
		print("音楽ファイルを再生(p) 終了する(q)")
		input_word = input(">>>")
		if input_word == "p" :
			print("順番に再生(f) シャッフルしてから再生(r)")
			input_word = input(">>>")
			# 順次再生
			if input_word == "f" :
				playnum = 0
				now = "play"
			# シャッフル後再生
			elif input_word == "r" :
				shufflemode = 1
				shuffle(playlist)
				playnum = 0
				now = "play"
			else :
				print("Error: fかrのいずれかを入力してください！")
		elif input_word == "q" :
			# 終了
			sys.exit()
		else :
			print("Error: pかqのいずれかを入力してください！")

	# 再生中の場合
	elif now == "play" :
		print("音楽再生中…(Ctrl+Cで終了)")
		while True :
			# 一曲が終了するまで待機
			if pygame.mixer.music.get_busy() :
				time.sleep(0.2)
				continue
			else :
				# 次の曲を再生
				play(playlist[playnum])
				playnum += 1
				if playnum >= len(playlist) :
					if shufflemode and shuffleswi == 1 :
						print("1ループしたのでシャッフルしなおします。")
						shuffle(playlist)
					else :
						print("1ループしたので初めから再生しなおします。")
					playnum = 0