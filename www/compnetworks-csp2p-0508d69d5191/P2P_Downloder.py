import string
from socket import *
import socket as TCPSocket
import sys
import concurrent.futures
import time

filename = sys.argv[1]
serverName = sys.argv[2]
serverPort = int(sys.argv[3])
def getPeers():
    UDPSocket = socket(AF_INET, SOCK_DGRAM)
    GET = "GET " + filename + ".torrent\n"
    UDPSocket.sendto(GET.encode(),(serverName, serverPort))
    header = ""
    while(True):
        header, address = UDPSocket.recvfrom(2048)
        if header != "":
            break
    UDPSocket.close()
    return header.decode().split("\n")

def getBlock(args):
    blockNum = args[0]
    peer = (args[1], args[2])
    clientSocket = TCPSocket.socket(TCPSocket.AF_INET, TCPSocket.SOCK_STREAM)
    clientSocket.connect((peer[0], peer[1]))
    clientSocket.settimeout(5)
    GET = "GET " + filename + ":" + str(blockNum) + "\n"
    clientSocket.send(GET.encode())
    head = bytearray(0)
    count = 0
    while (True):
        data = clientSocket.recv(1)
        head.extend(data)
        count += 1
        if count > 1:
            if (head[-1] == 10 and head[-2] == 10):
                break
    print(head)
    offset = head.decode().split('BODY_BYTE_OFFSET_IN_FILE: ')[1].strip(string.ascii_letters + "_: ").split('\n')[0]
    # print(int(offset))
    bodyLength = head.decode().split('BODY_BYTE_LENGTH: ')[1].strip(string.ascii_letters + "_: ")
    # print(bodyLength)
    body = bytearray(0)
    count2 = 0
    try:
        while count2 < int(bodyLength):
            # print(blockNum)
            data = clientSocket.recv(1024)
            body.extend(data)
            count2 += 1024
    except TCPSocket.timeout:
        clientSocket.close()
        return getBlock(args)
    clientSocket.close()
    return int(offset), body

fileData = getPeers()
print(fileData)
numBlocks = fileData[0]
fileSize = fileData[1]
peer1 = [fileData[2], fileData[3]]
peer2 = [fileData[4], fileData[5]]
print(numBlocks)
print(fileSize)
print(peer1)
print(peer2)
intBlocks = int(numBlocks.strip(string.ascii_letters+"_: "))
IP1 = peer1[0].split(" ")[1]
port1 = int(peer1[1].split(" ")[1])
IP2 = peer2[0].split(" ")[1]
port2 = int(peer2[1].split(" ")[1])
time.sleep(3)
rr = getPeers()
IP3 = rr[2].split(" ")[1]
port3 = int(rr[3].split(" ")[1])
IP4 = rr[4].split(" ")[1]
port4 = int(rr[5].split(" ")[1])
print(rr)
time.sleep(3)
rr = getPeers()
IP5 = rr[2].split(" ")[1]
port5 = int(rr[3].split(" ")[1])
IP6 = rr[4].split(" ")[1]
port6 = int(rr[5].split(" ")[1])
print(rr)

blocks = bytearray(0)
arr = []
o = []
with concurrent.futures.ThreadPoolExecutor() as executor:
    ips = [IP1, IP2, IP3, IP4,IP5]
    ports = [port1, port2, port3, port4, port5]
    s = 0
    while s < intBlocks:
        # print(s)
        c = s
        args = []
        args.append((s, ips[s % len(ips)], ports[s % len(ports)]))
        s += 1
        if s < intBlocks:
            args.append((s, ips[s % len(ips)], ports[s % len(ports)]))
            s += 1
            if s < intBlocks:
                args.append((s, ips[s % len(ips)], ports[s % len(ports)]))
                s += 1
                if s < intBlocks:
                    args.append((s, ips[s % len(ips)], ports[s % len(ports)]))
                    s += 1
                    if s < intBlocks:
                        args.append((s, ips[s % len(ips)], ports[s % len(ports)]))
                        s += 1
        results = executor.map(getBlock, args)
        for r in results:
            arr.append(r)
            c += 1
file = open(filename, "wb")
for a in arr:
    file.write(a[1])
file.close()