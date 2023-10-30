[TOC]

# README #

This repository consists of the source files for the [client-server and peer-to-peer downloader client programming assignments described here](https://bitbucket.org/compnetworks/csp2p-downloader/src/master/).

## Compiling the source ##

A pre-compiled jar `bin/PA1.jar` is included for convenience, and can be regenerated using one of the following options:

1. IDE: Load the source files in an IDE and build class files using it.
2. Unix command-line: 
	* Compile and place class files in `./bin`
	```
	javac -d bin src/pa1/*.java src/pa1/*/*.java src/pa1/util/*.java src/org/json/*.java
	```
	* Create jar file in the class files directory (`./bin` as above or wherever your IDE places class files)
	```
	jar cmf  ../Manifest.txt PA1.jar pa1 org
	```
	
## Running the server ##

The main file is `pa1.Main` and can be run using an IDE or one of the following two command-line options, the first of which should specify the directory (`bin`) where the class files generated above are stored:

1. ```java -cp <path/to/class/files> pa1.Main```
2. ```java -jar PA1.jar```

## Tips ##
* Ensure that the server is not already running, otherwise you will get an `java.net.BindException: Address already in use` excepttion.