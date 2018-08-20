#include <stdio.h>
#include <stdlib.h>

#define BUFFER_SIZE 48000*2*100
#define COMPARE_LEN 1000
// The files are slightly different; usually maximum of 1 difference
#define DIFFERENCE_THRESHOLD 5

// We look for at least COMPARE_LEN of close samples within
// DIFFERENCE_THRESHOLD and then declare those a match
// starting at the first sample. The function takes:
// Two buffers (buf1, buf2)
// A total length of the buffers
// And then returns the number of samples (each 4 bytes) of offset
long compare_blocks(short* buf1, short* buf2, long buffer_size) {
	long i, j;
	for (i = 0, j = 0; i < BUFFER_SIZE; i ++) {
		if (abs(buf1[i] - buf2[j]) <= DIFFERENCE_THRESHOLD)
			j++;
		else 
			j = 0;
		if (j > COMPARE_LEN) {
			long sample_delta = i - j + 1;
			return sample_delta/2;
		}
	}
	for (j = 0, i = 0; j < BUFFER_SIZE; j ++) {
		if (abs(buf1[i] - buf2[j]) <= DIFFERENCE_THRESHOLD)
			i++;
		else 
			i = 0;
		if (i > COMPARE_LEN) {
			long sample_delta = i - j - 1;
			return sample_delta/2;
		}
	}
	return -1;
}

void print_buffer(short* buf, long buffer_size) {
	long i;
	for (i = 0; i < buffer_size; i+=2) {
		printf("%hd\n",buf[i]);
	}
}

int main(int argc, char* argv[]) {
	FILE *f1, *f2;
	short *buf1, *buf2;
	if (argc != 3) {
		printf("Usage: %s FILE1 FILE2\n", argv[0]);
	}
	f1 = fopen(argv[1],"r");
	f2 = fopen(argv[2],"r");
	if (f1 == NULL || f2 == NULL) {
		fprintf(stderr,"Error: cannot open file\n");
		exit(1);
	}
	buf1 = malloc(BUFFER_SIZE*sizeof(short));
	buf2 = malloc(BUFFER_SIZE*sizeof(short));
	fread(buf1, sizeof(short), BUFFER_SIZE, f1);
	fread(buf2, sizeof(short), BUFFER_SIZE, f2);
	long offset = compare_blocks(buf1, buf2, BUFFER_SIZE);
	printf("%ld\n", offset);
	exit(0);
}
