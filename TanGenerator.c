#include <stdio.h>

int main()
{
    int i,j;
    int arr[100];
    for(i=0;i<100;i++)
    {
    int r =rand();
    arr[i]=r;
    printf("%d",r);
    printf("\n");
    }
    for (i=0;i<100;i++)
    {
    int srch=arr[i];
    for(j=i+1;j<100;j++)
    {
        if(srch== arr[j])
        {
        printf("Collision occured");
        break;
        }
        
    }
    }
    printf("end of program");
    return 0;
}

