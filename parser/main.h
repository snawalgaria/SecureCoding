//
// Created by Ralph Oliver Schaumann on 10/18/15.
//

#ifndef PARSER_MAIN_H
#define PARSER_MAIN_H

const int SIZE_3 = 3;
const int SIZE_4 = 4;
const int SIZE_6 = 6;
const int SIZE_7 = 7;

const char * iban = "IBAN";
const char * bic = "BIC";
const char * name = "NAME";
const char * amount = "AMOUNT";
const char * subject = "SUBJECT";
const char * tan = "TAN";
const char * date = "DATE";
const char * comment = "COMMENT";

void process(char *, size_t);

int cmp_string(char *, const char *, int, const int);

int parse_value(char *, char **, int);

void print_value(char *);

int validate_iban(char *);

int validate_bic(char *);

int validate_name(char *);

int validate_tan(char *);

int validate_amount(char *);

int validate_date(char *);

int validate_subject(char *);

int validate_comment(char *);

#endif //PARSER_MAIN_H
