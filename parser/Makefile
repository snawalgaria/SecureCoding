all: parser
parser: main.c
	gcc -o $@ $^ -I/usr/include/mysql  -DBIG_JOINS=1  -fno-strict-aliasing   -g -L/usr/lib/i386-linux-gnu -lmysqlclient -lpthread -lz -lm -lrt -ldl
clean:
	rm -rf parser