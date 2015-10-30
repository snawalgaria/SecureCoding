all: parser
parser: main.c
	gcc -g -I /usr/include/mysql  -L/usr/lib/mysql -lmysqlclient -o $@ $^
clean:
	rm -rf parser