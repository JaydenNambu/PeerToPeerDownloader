[TOC]

# Overview #

This two-part assignment is designed to introduce you to socket programming by making you implement a file downloader using a traditional client/server approach as well as a peer-to-peer approach.

**Goal**: Your goal is to write a network program, referred to as the *client*, that downloads an image file from a *server* that we maintain, and your client must implement support for the following two options:
	
1. Client/server option: Request the server for the entire file (similar in spirit to HTTP).
	
2. Peer-to-peer option: Request the server for addresses of other peers that possess parts of the file, called *blocks*, and download these blocks from different peers (similar in spririt to BitTorrent).

The following sections specify the protocol for the client/server option; the protocol for the peer-to-peer option; design constraints for each, submission and auto-grading instructions, and tips and FAQs.


# Part A: Client/server protocol #

In the client/server approach, the client should request the server for the entire file using TCP. We maintain a server running on the following <hostname, port number> combination: `<pear.cs.umass.edu, 18765>` for convenience. The [source code of the server](https://bitbucket.org/compnetworks/csp2p/src/master/) is public, so you can also clone and run the server yourself on your local machine during development and testing of your client.

## File request ##
The client must send a request in the following format to the server to download filename, where the `\n` at the end is the newline character, and `<filename>` should be replaced with the name of the file to be downloaded (without the `<` and `>` enclosers).


```
	GET <filename>\n
```

## File response ##
The server's response to a correctly formatted file request will have a header followed by the body. The *header* is terminated by two newline characters, i.e., the string `\n\n`, which also marks the beginning of the *body*. An example response is as follows:

```
	200 OK
	BODY_BYTE_OFFSET_IN_FILE: 0
	BODY_BYTE_LENGTH: 58241

	b^*%_JE@(u...<bytes of body follow here>
```

The example response above contains four lines of header interpreted as follows: The first line consists of a numeric status code (`200` above), whitespace, and a message (`OK` above) terminated by a newline character. The next line contains a header keyword `BODY_BYTE_OFFSET_IN_FILE` that specifies from what byte position in the file does the body (on the fifth line above) start. In the client/server mode, the server will always return the whole file in one piece starting from byte 0 (or the first byte), so you can simply ignore this line. The third line specifies the number of bytes in the body (similar to `Content-Length` in HTTP). The fifth line following the empty fourth line is where the bytes of the body start, so in the above example, the first byte (`b` above) on the fifth line is the first byte of the file and a total of 58241 bytes are being returned by the server in the body. 

The server will return `200 OK` for a correctly formatted request; for incorrectly formatted requests or requests for non-existent files, the server will return `400 BAD_FORMAT`. For testing and development, the server serves these two files: `test.jpg` and `redsox.jpg`.

## Part A Deliverable ##
Your client must programmatically "speak" the above protocol over a TCP socket connected to the `<IP,port>` specified as a command-line argument, retrieve all the bytes of the body of the `filename` also specified as a command-line argument (refer Submission instructions further below), store the retrieved body in a local file of the same name in the current directory, and then exit gracefully.

# Part B: Peer-to-peer protocol #

In the peer-to-peer approach, the client must first obtain the *torrent metadata* from the torrent server (a.k.a *tracker*) and use this metadata to download data blocks. The torrent metadata contains information about the number and size of blocks constituting the file and peers (`<IP,port>` tuples) from which the blocks may be downloaded.


## Torrent metadata ##

**Torrent metadata request format**: The client must download the torrent metadata for filename by sending a UDP message in the following format:

```
	GET <filename>.torrent\n
```
Thus, to request the torrent metadata for `redsox.jpg`, the client must send a UDP message containing the string `GET redsox.jpg.torrent` (with or without a newline at the end) to the torrent server.

This UDP-based torrent server is running at `<date.cs.umass.edu, 19876>`. 

**Torrent metadata response format**: The response to the request for torrent metadata is in the following format:

```
	NUM_BLOCKS: 6
	FILE_SIZE: 58241
	IP1: 128.119.245.20
	PORT1: 3456
	IP2: 128.119.245.20
	PORT2: 4321
```

The names of the fields above are self-explanatory. `NUM_BLOCKS` is the number of blocks in the requested file. `FILE_SIZE` is the size of the entire file in bytes. `IP1` and `PORT1` identify the IP address and port number of the first peer, and `IP2` and `PORT2` the second peer.

Each response will contain two randomly chosen valid peer identifiers. You can query the tracker multiple times to get more peer identifiers. However, the tracker is designed to rate-limit the queries, so you may not get responses promptly if you send requests too fast or may not get responses at all as UDP messages can get lost.

## Data blocks ##

Having obtained metadata information using UDP as above, data blocks must be requested using TCP as follows.

**Block request format**: The following request fetches a specific block

```
	GET filename:<block_number>\n
```

where block_number is an integer identifying the block in filename. For example, you may request block 24 in redsox.jpg by sending the string `GET Redsox.jpg:24\n` (note: without any intervening whitespace) to any one of the peers received in the torrent metadata above. Note that the servers listed in the client/server option also act as peers and support the above request format to request specific blocks.

Specifying `*’ instead of a block number returns a randomly chosen block

```
	GET filename:*\n
```

**Block response format**: The response to a block request has the following format as that of the whole body. The only difference is that the starting byte offset in the file in general will be non-zero and the size of the block will be much smaller than the size of the file, for example:

```
	200 OK
	BODY_BYTE_OFFSET_IN_FILE: 20000
	BODY_BYTE_LENGTH: 10000

	^#@gdhh#...<bytes of body follow here>
```

All blocks except the last block will be of the same size.

## Part B Deliverable ##
Your client must implement the torrent metadata protocol over a UDP socket to the torrent server's `<IP,port>` specified as a command-line argument, learn `<IP,port` peer tuples from the torrent server, retrieve all the blocks of the `filename` specified as a command-line argument (refer Submission instructions further below), store the retrieved body in a local file of the same name in the current directory, and then exit gracefully.

Speed is of essence in the peer-to-peer approach, so your client must implement a strategy to download the file as fast as possible. The server ports on the peer-to-peer server are intentionally rate-limited to send data slower compared to the client-server server, so you will need to use the peer-to-peer approach to download the file in a reasonable amount of time.


# Submission Instructions #

1. You must submit your client program. For part A, name the main file `CSDownloader.<appropriate-extension>`, e.g., `CSDownloader.py` for python. For part B, name the main file `P2PDownloader.<appropriate-extension>`. Feel free to code up additional helper files to this main file as needed. 

2. You can use any programming language of your choice. The nice part about network programming using the socket API is that the server and client may be written in different programming languages.

3. Your client must implement support for the following command-line argument format. For example, if you are using java or python or C, we should be able to invoke your client as follows respectively for parts A and B:
```
        java CSDownloader <filename> <clientServerIP> <clientServerPort>
        java P2PDownloader <filename> <torrentServerIP> <torrentServerPort>
		
		python CSDownloader.py <filename> <clientServerIP> <clientServerPort>
        python P2PDownloader.py <filename> <torrentServerIP> <torrentServerPort>
		
		CSDownloader.o <filename> <clientServerIP> <clientServerPort>
        P2PDownloader.o <filename> <torrentServerIP> <torrentServerPort>
```
	Note that the IP and port are those of the TCP-based file server in part A and the UDP-based torrent metadata server in part B. *Do not hardcode any filenames or IP/port tuples in your code as your code may be tested with different files and servers than the ones provided for development*.

4. For both parts A and B, your client must dowload the specified file into a file of the same name in the current directory.

5. Submit all your code files with your appropriately named client program in the top-level directory.

# Tips, FAQs, etc. #

Refer [here](TIPS.md).