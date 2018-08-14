#include <stdio.h>
#include <time.h>
#include <string.h>
#include <fcntl.h>
#include <string.h>

#define BUFSIZE 4*48000 //1s at 2 channels * 2 bytes/sample * 2 bytes/unsigned short


char newtime[100], curtime[100];

struct tm tptr;
time_t t;
FILE *f = NULL;

void switch_file();

int main()
{
	/*printf("Int is %d", sizeof(unsigned short));
	return 0;*/
	int fd;
	int c;
	//unsigned long reads_remaining;
	unsigned short buffer[BUFSIZE + 1];

	while (1) {
		switch_file();
		c = fread(buffer, sizeof(unsigned short), BUFSIZE, stdin);
		//printf("%d bytes read", c);
		fwrite(buffer, sizeof(unsigned short), c, f);
	}
		//printf("Done reading");
	
	return 0;
}

void switch_file() {
	//printf("switching file");
	char filename[100];

	t = time(NULL);

	gmtime_r(&t, &tptr);

	strftime(newtime, 100, "%Y-%m-%d_%HU", &tptr);
	//printf("%s", newtime);
	if (strncmp(curtime, newtime, 100) != 0) {
		if (f != NULL)
			fclose(f);
		strncpy(curtime,newtime,100);
		snprintf(filename,100,"/home/wmfo-admin/archives/%s.s16",curtime);
		f = fopen(filename, "a");
	}

	return;
}

