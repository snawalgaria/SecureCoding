//
// Created by Ralph Oliver Schaumann on 10/18/15.
//

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

#include "main.h"

void process(char * buffer, size_t size){
    int i;

    char * iban_val = NULL;
    char * bic_val = NULL;
    char * amount_val = NULL;
    char * subject_val = NULL;
    char * tan_val = NULL;
    char * date_val = NULL;
    char * comment_val = NULL;

    for(i = 0; i < size; ++i){
        if(buffer[i]=='I'){
            if(!cmp_string(buffer,iban,i,SIZE_4)){
                printf("ERROR: invalid parameter in file at position %i (IBAN)",i);
                return;
            }
            i += SIZE_4;
            i = parse_value(buffer,&iban_val,i);
        }
        else if(buffer[i]=='B'){
            if(!cmp_string(buffer,bic,i,SIZE_3)){
                printf("ERROR: invalid parameter in file at position %i (BIC)",i);
                return;
            }
            i += SIZE_3;
            i = parse_value(buffer,&bic_val,i);
        }
        else if(buffer[i]=='T'){
            if(!cmp_string(buffer,tan,i,SIZE_3)){
                printf("ERROR: invalid parameter in file at position %i (TAN)",i);
                return;
            }
            i += SIZE_3;
            i = parse_value(buffer,&tan_val,i);
        }
        else if(buffer[i]=='A'){
            if(!cmp_string(buffer,amount,i,SIZE_6)){
                printf("ERROR: invalid parameter in file at position %i (AMOUNT)",i);
                return;
            }
            i += SIZE_6;
            i = parse_value(buffer,&amount_val,i);
        }
        else if(buffer[i]=='D'){
            if(!cmp_string(buffer,date,i,SIZE_4)){
                printf("ERROR: invalid parameter in file at position %i (DATE)",i);
                return;
            }
            i += SIZE_4;
            i = parse_value(buffer,&date_val,i);
        }
        else if(buffer[i]=='S'){
            if(!cmp_string(buffer,subject,i,SIZE_7)){
                printf("ERROR: invalid parameter in file at position %i (SUBJECT)",i);
                return;
            }
            i += SIZE_7;
            i = parse_value(buffer,&subject_val,i);
        }
        else if(buffer[i]=='C'){
            if(!cmp_string(buffer,comment,i,SIZE_7)){
                printf("ERROR: invalid parameter in file at position %i",i);
                return;
            }
            i += SIZE_7;
            i = parse_value(buffer,&comment_val,i);
        }
        else {
            printf("ERROR: invalid parameter in file at position %i",i);
            return;
        }
        if(++i == -1){
            printf("ERROR: illegal character ';' %c",0);
        }
    }

    //validate input
    if(!validate_iban(iban_val)){
        printf("ERROR: invalid IBAN:\n'%s'",iban_val);
        return;
    }
    if(!validate_bic(bic_val)){
        printf("ERROR: invalid BIC:\n'%s'",bic_val);
        return;
    }
    if(!validate_tan(tan_val)){
        printf("ERROR: invalid TAN:\n'%s'",tan_val);
        return;
    }
    if(!validate_amount(amount_val)){
        printf("ERROR: invalid amount:\n'%s'",amount_val);
        return;
    }
    if(!validate_date(date_val)){
        printf("ERROR: invalid date:\n'%s'",date_val);
        return;
    }
    if(!validate_subject(subject_val)){
        printf("ERROR: invalid subject:\n'%s'",subject_val);
        return;
    }
    if(!validate_comment(comment_val)){
        printf("ERROR: invalid comment:\n'%s'",comment_val);
        return;
    }

    //print out provided values
    print_value(iban_val);
    print_value(bic_val);
    print_value(tan_val);
    print_value(amount_val);
    print_value(date_val);
    print_value(subject_val);
    print_value(comment_val);
}

int cmp_string(char * actual, const char * expected, int pos, const int size){
    int i;
    for(i = 0; i < size; ++i)
        if(actual[pos + i] != expected[i])
            return 0;
    return 1;
}

int parse_value(char * buffer, char ** target, int index){
    if(buffer[index++] != '=' || buffer[index++] != '"'){
        printf("ERROR: invalid format");
        return -1;
    }
    target[0] = (buffer + index);
    while(buffer[index] != '"'){
        if(buffer[index++] == ';')
            return -1;
    }
    buffer[index] = 0;
    return index;
}

int validate_iban(char *val){
    int length = -1;
    int cur;
    while((cur = val[++length])){
        if(!(cur >= '0' && cur < ':') &&
           !(cur >= 'a' && cur < 'z') &&
           !(cur >= 'A' && cur < 'Z'))
            return 0;
    }
    return length < 35;
}

int validate_bic(char * val){
    int length = -1;
    int cur;
    while((cur = val[++length])){
        if(!(cur >= '0' && cur < ':') &&
           //!(cur >= 'a' && cur < 'z') &&
           !(cur >= 'A' && cur < 'Z'))
            return 0;
    }
    return length >= 8 && length < 13;
}

int validate_tan(char * val){
    int length = -1;
    int cur;
    while((cur = val[++length])){
        if(!(cur >= '0' && cur < ':') &&
           //!(cur >= 'a' && cur < 'z') &&
           !(cur >= 'A' && cur < 'Z'))
            return 0;
    }
    return length != 15;
}

int validate_amount(char * val){
    //TODO
    int length = -1;
    int cur;
    while((cur = val[++length])){
        if(!(cur >= '0' && cur < ':') &&
           //!(cur >= 'a' && cur < 'z') &&
           !(cur >= 'A' && cur < 'Z') &&
                cur != ',' && cur != '.')
            return 0;
    }
    return 1;
}

int validate_date(char * val){
    int length = -1;
    while(val[++length]);
    if(length != 19)
        return 0;
    if(!(val[0] >= '0' && val[0] < '4'))
        return 0;
    if(!(val[1] >= '0' && val[1] < ':'))
        return 0;
    //2
    if(!(val[3] >= '0' && val[3] < '2'))
        return 0;
    if(!(val[4] >= '0' && val[4] < ':'))
        return 0;
    //5
    if(!(val[6] >= '0' && val[6] < ':'))
        return 0;
    if(!(val[7] >= '0' && val[7] < ':'))
        return 0;
    if(!(val[8] >= '0' && val[8] < ':'))
        return 0;
    if(!(val[9] >= '0' && val[9] < ':'))
        return 0;
    //10
    if(!(val[11] >= '0' && val[11] < '6'))
        return 0;
    if(!(val[12] >= '0' && val[12] < ':'))
        return 0;
    //13
    if(!(val[14] >= '0' && val[14] < '6'))
        return 0;
    if(!(val[15] >= '0' && val[15] < ':'))
        return 0;
    //16
    if(!(val[17] >= '0' && val[17] < '3'))
        return 0;
    if(!(val[18] >= '0' && val[18] < ':'))
        return 0;

    return 1;
}

int validate_subject(char * val){
    int length = -1;
    while(val[++length]);
    return length <= 160;
}

int validate_comment(char * val){
    int length = -1;
    while(val[++length]);
    return length <= 160;
}

void print_value(char * ptr){
    int i = -1;
    while (ptr[++i])
        if(ptr[i] == '\n') printf("\\n");
        else printf("%c",ptr[i]);
    printf(";%c",0);
}

int main(int argc, char * argv[]){

    FILE *file_handle;

    //check parameters
    if(argc != 2){
        printf("ERROR: no filename supplied");
        return 0;
    }
    //check file exists
    if( access(argv[1],F_OK) == -1){
        printf("ERROR: file '%s' does not exist",argv[1]);
        return 0;
    }
    //check file readable
    if( access(argv[1],R_OK) == -1){
        printf("ERROR: file '%s' is not readable",argv[1]);
        return 0;
    }
    //open file
    if((file_handle = fopen(argv[1], "r"))){
        //get the file size
        fseek(file_handle,0L,SEEK_END);
        size_t size = (size_t) ftell(file_handle);
        rewind(file_handle);
        if(size > 0){
            //load the contents
            char buffer[size + sizeof(int)];
            buffer[size]=0;
            fread(buffer,size,1, file_handle);
            fclose(file_handle);

            process(buffer,size);

        } else fclose(file_handle);
    }

    return 0;
}
