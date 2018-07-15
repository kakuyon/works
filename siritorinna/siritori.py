import time
import pyperclip

hira = 'あいうえおかきくけこさしすせそたちつてとなにぬねのはひふへほまみむめもやゆよらりるれろわをんがぎぐげござじずぜぞだぢづでどばびぶべぼぱぴぷぺぽ'
kana = 'アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲンガギグゲゴザジズゼゾダヂヅデドバビブベボパピプペポ'
small_hira = 'ぁぃぅぇぉかきくけこさしすせそたちってとなにぬねのはひふへほまみむめもゃゅょ'
small_kana = 'ァィゥェォカキクケコサシスセソタチッテトナニヌネノハヒフヘホマミムメモャュョ'

translate = []
for a, b in zip(hira, kana):
	translate.append([a, b])
for i, before in enumerate(translate):
	before.append(small_hira[i])
	before.append(small_kana[i])
	translate[i] = before
	if small_hira[i] == 'ょ': break


alert_texts = ['しっ知らない言葉使ったって偉くなんかないんだからね！',
'その言葉りんなわかんない！\nちょっと違う言葉もう１回言ってみて？',
'ちょっと！知らない言葉使わないでよ！？\n別の言葉でやりなおして！？',
'りんなの知ってる言葉じゃないと受け付けませーん…\nちがう言葉でもう１回！',
'ちょっと何言ってるかよくわかりませんね…？\nやり直しかな( ´_ゝ｀)',
'りんなの知ってる言葉じゃなきゃだめ～やりなお～～し',
'なにそれ！りんな知らない言葉聞くとくしゃみ出ちゃうｗ\nもっかい！やりなおしー',
'ナポレオンの辞書にはあってもりんなの辞書にはないな！！怒\nやりなおーし！',
'その言葉知らないもん！\n別の言葉でやりなおし！',
'残念ながらその言葉はりんなの脳内に存在しませんね～\nやり直し！',
'余の辞書にはない！\nもっかい！',
'ボケたつもり？まじめにやってよね！'
]

global f
global flag2
flag2 = 1
global new_lines
global used_word
used_word = []
global tail_list
tail_list = []
global flag3
flag3 = 0

#ちょっと何言ってるかよくわかりませんね…？
#やり直しかな( ´_ゝ｀)
def return_answer(text):
	global flag2
	print('start')
	if(True):#text[0] in hira+kana or alert_texts[9] in text or alert_texts[10] in text
		global line
		global flag3
		for serif in alert_texts:
			if serif in text:
				print('!!!')
				flag3 = 1

		if flag3: flag3 = 0
		else:
			num = text.find('[')
			if(num != -1): tail = text[num-2] if text[num-2] != 'ー' else text[num-3]
			else: tail = text[-1] if text[num-1] != 'ー' else text[num-2]
			if tail not in hira+kana+small_hira+small_kana:
				tail = 'ん'
			save_rinna_word(text)
			create_tail_list(tail)
			print('tail: ' + tail)

		print('tail_list: ', end="")
		print(tail_list)
		for t in tail_list:
			print('t: ' + t)
			with open('siritolist.txt', 'r', encoding='utf8') as f:
				new_lines = []
				global getline
				line = f.readline()
				global flg
				flg = 0
				while line:
					if(line[0] == t):
						if (flg == 0 and line):
							print('line : ' + line)
							if (used_word.count(all_hira(line).split()) == 0):
								flag2 = 0
								line.replace('_', 'ー')
								line.replace('＿', 'ー')
								pyperclip.copy(line[:-1])
								used_word.append(all_hira(line).split())
								flg = 1
								print('find!')
							else:
								print('miss : ', end='')
								print(used_word)
					new_lines.append(line)
					line = f.readline()

			with open('siritolist.txt', 'w', encoding='utf-8') as f :
				f.write('')
			print('check flag2: ' + str(flag2))
			with open('siritolist.txt', 'a', encoding='utf-8') as f :
				for line in new_lines:
					f.write(line)
			if not flag2: break

def create_tail_list(tail):
	global tail_list
	tail_list = [tail]
	print('up tail_list_method')
	for words in translate:
		if tail in words:
			tail_list = words

def all_hira(word):
	words = ''
	for ch in word:
		for trans in translate:
			if ch in trans:
				ch = trans[0]
		words += ch
	return words

def save_rinna_word(text):
	s = text.find('[')
	if s != -1:
		word = text[:s]
	else:
		word = text.split()[-1]
	used_word.append(word.split())
	print('saved ' + word)


if __name__ == '__main__' :
	global copy_text
	copy_text = pyperclip.paste()
	print('start')
	while(True) :
		time.sleep(0.2)
		if copy_text != pyperclip.paste():
			if flag2:
				copy_text = pyperclip.paste()
				return_answer(copy_text)
				print('exit! flag2: ' + str(flag2))
			else:
				print('flag2 is 0...')
				flag2 = 1
				copy_text = pyperclip.paste()