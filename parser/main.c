//
// Created by Ralph Oliver Schaumann on 10/18/15.
//

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <time.h>
#include <mysql.h>

const char * target = "TOACCOUNT:";
const int target_size = 10;
const char * amount = "AMOUNT:";
const int amount_size = 7;
const char * subject = "SUBJECT:";
const int subject_size = 8;
const char * tancode = "TAN:";
const int tan_size = 4;


// Returns consumed
size_t parse(char* buf, size_t remaining) {
    char* rbuf = buf;
    size_t consumed = 0;
    while (remaining > 0 && rbuf[0] != '\n') {
        rbuf++;
        consumed++;
        remaining--;
    }
    // unproblematic, because we allocate more memory than we need.
    rbuf[0] = 0; // was \n
    return consumed+1;
}

int validate(char* value, const char* allowed) {
    char cur;
    char* original = value;
    while ((cur = (*(value++)))) {
        if (!strchr(allowed, cur)) return 0;
    }
    return strlen(original) > 0 ? 1 : 0;
}

int validate_amount(char* value) {
    char* dot = strchr(value, '.');
    if (dot) {
        dot++;
        if (value + strlen(value) - 2 != dot) return 0;

        // If we have a dot, ensure that we have only one
        if (strchr(dot, '.')) return 0;

        // Remove dot
        memmove(dot - 1, dot, 2); // We hopefully ensured that strlen(dot) = 2.
    } else {
        strcat(value, "00"); // We have that buffer.
    }

    return 1;
}

const char* outputStrings[] = {
    "",
    "",
    "<h1>File error</h1><p>Your file does not match the specification.</p>",
    "",
    "<h1>Internal error</h1><p>Please inform the bank about uploading issues.</p>",
    "",
    "<h1>Internal error</h1><p>Please inform the bank about database issues.</p>",
    "<h1>Fools!</h1><p>You just tried to spend more money than you have!</p>",
    "<h1>Invalid data</h1><p>Some data in your file is not valid.</p>"
};
__attribute__((noreturn)) void __exitout(int code) {
    if (code <= 8 && code >= 0) printf("%s", outputStrings[code]);
    else printf("<h1>Unknown error</h1><p>Exit Code %d</p>", code);
    printf("\n");
    exit(code);
}

#define DEBUG 1

#define checksqlerror() do {if (my == NULL || mysql_errno(my) != 0){if (my != NULL) mysql_close(my);__exitout(6);}}while(0)
#define doerror() do {if (my != NULL) mysql_close(my);__exitout(8);}while(0)

static void
fetch_column(MYSQL* my, const char* query, const char* column, MYSQL_FIELD** out_field, char** outdata) {
    mysql_real_query(my, query, strlen(query));
    checksqlerror();

    MYSQL_RES* res = mysql_store_result(my);
    checksqlerror();

    int fieldindex;

    MYSQL_FIELD* field;
    for (fieldindex = 0; fieldindex < mysql_num_fields(res); fieldindex++) {
        field = mysql_fetch_field(res);
        if (strcmp(column, (field)->name) == 0) {
            break;
        }
    }
    if (fieldindex == mysql_num_fields(res)) doerror();

    int rowcount = 0;
    MYSQL_ROW row;

    while ((row = mysql_fetch_row(res)) != NULL) {
        rowcount++;
        if (rowcount > 1) doerror();
        *outdata = row[fieldindex];
    }

    if (rowcount == 0) doerror(); 

    if (out_field != NULL) *out_field = field;
    // printf("HERE %x %x %s\n", my, mysql_errno(my), mysql_error(my));
}

static void
perform_transaction(int source, int target, int amount, char* tanstr, char* subject)
{
    char query[512];

    MYSQL* my = mysql_init(NULL);
    checksqlerror();

    mysql_real_connect(my, "localhost", "scbanking", "thisisasupersecurepassword", "scbanking", 0, NULL, 0);
    checksqlerror();

    char* resbuf;
    int sourceBalance;
    int targetBalance;

    sprintf(query, "SELECT balance FROM accounts WHERE userid = %d;", source);
    fetch_column(my, query, "balance", NULL, &resbuf);
    sourceBalance = atoi(resbuf);

    sprintf(query, "SELECT balance FROM accounts WHERE userid = %d;", target);
    fetch_column(my, query, "balance", NULL, &resbuf);
    targetBalance = atoi(resbuf);

    sprintf(query, "SELECT tan FROM tans WHERE tan = '%s' AND userid = %d AND used = 0;", tanstr, source);
    fetch_column(my, query, "tan", NULL, &resbuf);

    if (sourceBalance - amount < 0) {
        __exitout(7);
    }

    sprintf(query, "INSERT INTO transactions (sourceAccount,targetAccount,volume,description,unixtime,isVerified) VALUES (%d, %d, %d, '%s', %d, %d);", source, target, amount, subject, (int)time(NULL), amount < 1000000 ? 1 : 0);
    mysql_query(my, query);
    checksqlerror();
    sprintf(query, "UPDATE tans SET used = 1 WHERE tan = '%s';", tanstr);
    mysql_query(my, query);
    checksqlerror();
    if (amount < 1000000) {
        sprintf(query, "UPDATE accounts SET balance = balance - %d WHERE userid = %d", amount, source);
        mysql_query(my, query);
        checksqlerror();
        sprintf(query, "UPDATE accounts SET balance = balance + %d WHERE userid = %d", amount, target);
        mysql_query(my, query);
        checksqlerror();
    }
}

__attribute__((noreturn)) void
process(char* userid, char * buffer, size_t size) {
    char* target_val = NULL;
    char* amount_val = NULL;
    char* subject_val = NULL;
    char* tan_val = NULL;

    long int remaining = size;
    char* rbuf = buffer;

    while (remaining >= 0 && rbuf[0] != 0) {
        if (remaining >= target_size && !strncmp(rbuf, target, target_size)) {
            target_val = rbuf + target_size;
            size_t consumed = parse(rbuf + target_size, remaining - target_size);
            rbuf += consumed + target_size;
            remaining -= consumed + target_size;
            if (!validate(target_val, "0123456789")) __exitout(2);
        }
        else if (remaining >= amount_size && !strncmp(rbuf, amount, amount_size)) {
            amount_val = rbuf + amount_size;
            size_t consumed = parse(rbuf + amount_size, remaining - amount_size);
            rbuf += consumed + amount_size;
            remaining -= consumed + amount_size;
            char* newamount = (char*) malloc(sizeof(char) * (strlen(amount_val) + 3));
            strcpy(newamount, amount_val);
            amount_val = newamount;
            if (!validate(amount_val, "0123456789.") || !validate_amount(amount_val)) __exitout(2);
        }
        else if (remaining >= tan_size && !strncmp(rbuf, tancode, tan_size)) {
            tan_val = rbuf + tan_size;
            size_t consumed = parse(rbuf + tan_size, remaining - tan_size);
            rbuf += consumed + tan_size;
            remaining -= consumed + tan_size;
            if (strlen(tan_val) != 15 || !validate(tan_val, "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNONPQRSTUVWXYZ.,;:!/()=?*_-#+")) __exitout(2);
        }
        else if (remaining >= subject_size && !strncmp(rbuf, subject, subject_size)) {
            subject_val = rbuf + subject_size;
            size_t consumed = parse(rbuf + subject_size, remaining - subject_size);
            rbuf += consumed + subject_size;
            remaining -= consumed + subject_size;
            if (strlen(subject_val) > 160 || !validate(subject_val, "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNONPQRSTUVWXYZ.,;:/() ")) __exitout(2);
        }
        else if (rbuf[0] == '\n') {
            rbuf++;
            remaining--;
        }
        else {
            __exitout(2);
        }
    }

    if (target_val == NULL || tan_val == NULL || amount_val == NULL || subject_val == NULL) {
        __exitout(2);
    }

    int target = atoi(target_val);
    int source = atoi(userid);
    int amount = atoi(amount_val);

    if (target == 0 || source == 0 || amount == 0) {
        __exitout(2);
    }

    perform_transaction(source, target, amount, tan_val, subject_val);
    printf("<h1>Success!</h1>Performed transaction to %d with %d euro-cents.", target, amount);
    __exitout(0);
}

int main(int argc, char * argv[]) {

    FILE *file_handle;
    char* filename;

    //check parameters
    if (argc != 3) {
        __exitout(4);
    }

    filename = argv[2];

    //check file exists
    if (access(filename,F_OK) == -1) {
        __exitout(4);
    }
    //check file readable
    if (access(filename,R_OK) == -1) {
        __exitout(4);
    }
    //open file
    if ((file_handle = fopen(filename, "r"))) {
        //get the file size
        fseek(file_handle, 0, SEEK_END);
        size_t size = (size_t) ftell(file_handle);
        rewind(file_handle);
        if(size > 0 && size < 3000) {
            // No need to free this.
            char* buffer = (char*) malloc((size + 1) * sizeof(char));
            buffer[size] = 0;
            fread(buffer, size, 1, file_handle);
            fclose(file_handle);
            process(argv[1], buffer,size);
            // We never get here.
        } else fclose(file_handle);
    }

    return 4;
}
// Exit codes: 2 parser error, 3 extra data, 4 i/o error
