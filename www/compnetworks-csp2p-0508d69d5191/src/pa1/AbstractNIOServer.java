package pa1;


import java.io.IOException;
import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.nio.ByteBuffer;
import java.nio.channels.*;
import java.nio.channels.spi.AbstractSelectableChannel;
import java.util.HashMap;
import java.util.HashSet;
import java.util.Iterator;
import java.util.Map.Entry;
import java.util.Set;
import java.util.logging.Logger;

/**
 * @author V. Arun
 */
/*
 * This class is an abstract class for any non-blocking I/IO
 * server that is listening on one or more ports and is
 * expecting requests terminated by a request separator.
 * Both FileServer and GradingServer extend this abstract
 * class. Upon receiving a request, the abstract method
 * executeRequest is called for processing the request.
 */
public abstract class AbstractNIOServer implements Runnable {
	public static final int DEFAULT_MAX_REQUEST_SIZE = 1024;

	private final int maxRequestSize;
	private final String requestSplitterRegex;
	private final Selector selector;
	private final int maxConnectionsPerIP = 5;
	protected final HashMap<Integer, ServerSocketChannel> portToServers =
			new HashMap<Integer, ServerSocketChannel>();
	protected final HashMap<ServerSocketChannel, Set<SocketChannel>> serverToConnections =
			new HashMap<ServerSocketChannel, Set<SocketChannel>>();
	protected final HashMap<InetAddress, Set<SocketChannel>> clientToConnections =
			new HashMap<InetAddress, Set<SocketChannel>>();

	protected static Logger log = Logger.getLogger(AbstractNIOServer.class.getName
			());

	// Begin abstract methods
	protected abstract void processAcceptedConnection(SocketChannel connChannel);

	protected abstract void executeRequest(String request,
			SocketChannel connChannel);

	// End abstract methods

	public AbstractNIOServer(Set<Integer> ports) throws IOException {
		this(ports, Config.DEFAULT_REQUEST_SPLITTER_REGEX,
				DEFAULT_MAX_REQUEST_SIZE);
	}

	public AbstractNIOServer(int port) throws IOException {
		this(port, Config.DEFAULT_REQUEST_SPLITTER_REGEX,
				DEFAULT_MAX_REQUEST_SIZE);
	}

	public AbstractNIOServer(int port, String requestSplitterRegex,
			int maxRequestSize) throws IOException {
		this(getSingleIntegerSet(port), requestSplitterRegex, maxRequestSize);
	}

	public AbstractNIOServer(Set<Integer> ports, String requestSplitterRegex,
			int maxRequestSize) throws IOException {
		this.selector = Selector.open();
		for (int port : ports)
			this.openServer(port); // exceptions self-contained
		this.maxRequestSize = maxRequestSize;
		this.requestSplitterRegex = requestSplitterRegex;
		log.info("Initiated NIO servers");
	}

	public void run() {
		while (true) {
			try {
				this.selector.select();
				Iterator<SelectionKey> keysIter =
						selector.selectedKeys().iterator();
				while (keysIter.hasNext()) {
					SelectionKey selKey = (SelectionKey) keysIter.next();
					keysIter.remove();
					try {
						if (selKey.isAcceptable()) {
							processAccept(selKey);
						}
						else if (selKey.isReadable()) {
							if(processClientRequest(selKey) == -1) {
								log.info("End of stream likely because " +
										"client closed socket");
							}
						}
					} catch (IOException e) {
						// all normal, no need to printStackTrace here
						log.info("Encountered exception on channel " +
										(SocketChannel)selKey.channel() + ": " +
										e.getMessage());
						this.closeClientChannel((SocketChannel)selKey.channel());
						selKey.cancel(); // unregister keys if exception
					} catch(CancelledKeyException cke) {
						// do nothing
						log.info("Ignoring cancelled key exception " + cke);
					}
				}
			} catch (IOException e) {
				// all normal, no need to printStackTrace here
				log.info("Encountered exception in main select loop; " +
						"continuing");
				e.printStackTrace();
			}
		}
	}



	protected synchronized ServerSocketChannel openServer(int port) {
		ServerSocketChannel ssChannel = null;
		try {
			ssChannel = ServerSocketChannel.open();
			ssChannel.configureBlocking(false);
			ssChannel.socket().bind(new InetSocketAddress(port));

			ssChannel.register(this.selector, SelectionKey.OP_ACCEPT);
			this.portToServers.put(port, ssChannel);
		} catch (IOException e) {
			log.severe("IOException while opening server on port " + port);
			if(ssChannel!=null) closeServerChannel(ssChannel);
			e.printStackTrace();
		}
		return ssChannel;
	}

	protected synchronized void shutdown() throws IOException {
		if (this.portToServers.isEmpty()) return;
		for (ServerSocketChannel ssChannel : this.portToServers.values()) {
			Set<SocketChannel> connChannels =
					this.serverToConnections.get(ssChannel);
			if (connChannels == null) return;
			for (SocketChannel connChannel : connChannels) {
				this.closeClientChannel(connChannel);
			}
		}
	}

	protected synchronized boolean closeClientChannel(
			final SocketChannel sockChannel) {
		boolean closed = false;
		try {
			SelectionKey key = sockChannel.keyFor(this.selector);
			if(key!=null) key.cancel();
			sockChannel.socket().close();
			sockChannel.close();
			closed = this.removeConnection(sockChannel);
		} catch (IOException e) {
			log.severe("Error while closing client channel:" +
					sockChannel.socket().getInetAddress() + ":" +
					sockChannel.socket().getPort() + e.getMessage());
		}
		return closed;
	}

	protected synchronized boolean closeServerChannel(ServerSocketChannel
															  serverChannel) {
		boolean closed = false;
		try {
			SelectionKey key = serverChannel.keyFor(this.selector);
			if (key != null) key.cancel();
			serverChannel.socket().close();
			serverChannel.close();
			this.portToServers.remove(serverChannel);
			closed = (this.serverToConnections.remove(serverChannel) != null);
		} catch (IOException e) {
			log.severe("Error while closing server channel:" + serverChannel
					.socket().getInetAddress() + ":" + serverChannel.socket()
					.getLocalPort() + e.getMessage());
			e.printStackTrace();
		}
		return closed;
	}

	/*************** Private methods below *******************/

	private int processClientRequest(SelectionKey selKey) throws IOException {
		SocketChannel service = (SocketChannel) selKey.channel();
		byte[] readFromChannel = new byte[maxRequestSize];
		ByteBuffer buf = ByteBuffer.wrap(readFromChannel);

		int numBytesRead = service.read(buf);
		if (numBytesRead == -1) this.closeClientChannel(service);
		if (numBytesRead <= 0) return numBytesRead; // includes -1

		String partRequest =
				new String(readFromChannel, 0, numBytesRead, "ISO-8859-1"); // only byte[]->String conversion
		StringBuffer sockBuf =
				((StringBuffer) selKey.attachment()).append(partRequest);
		assert (sockBuf.toString().endsWith(partRequest));
		String[] requests = sockBuf.toString().split(requestSplitterRegex);

		// discard empty requests and close connection
		if (requests.length == 0) {
			log.info("Received [" + partRequest + "] on " + service + ", " +
					"closing connection");
			this.closeClientChannel(service);
			return numBytesRead;
		}

		// execute substrings split by regex except for the last one
		if (partRequest.matches("(.*" + requestSplitterRegex + ")+.*")) {
			for (int i = 0; i < requests.length - 1; i++) { // all but last
				executeRequest(requests[i], service);
			}
			sockBuf.delete(0, sockBuf.capacity());

			// execute last part if ends with regex, else append to buffer
			if (partRequest.matches("(.*" + requestSplitterRegex + ")+"))
				executeRequest(requests[requests.length - 1], service);
			else sockBuf.append(requests[requests.length - 1]);
		}

		if (sockBuf.length() > 0)
			log.info("Received partial request (without newline) <" +
					sockBuf.toString() + "> on" + service );

		// prevent memory exhaustion
		if (sockBuf.length() > maxRequestSize) {
			log.warning("Received too long a command from " +
					service.socket().getInetAddress() + ":" +
					service.socket().getPort() + ", closing connection");
			this.closeClientChannel(service);
		}
		return numBytesRead;
	}

	private boolean processAccept(SelectionKey selKey) throws IOException {
		// Get channel with connection request
		ServerSocketChannel server = (ServerSocketChannel) selKey.channel();

		// Accept the connection
		SocketChannel connChannel = server.accept();
		log.info("Accepted connection " + connChannel);
		if (!this.isAcceptable(connChannel)) {
			log.info("closing " + connChannel + "; numOpenConnections=" +
					numOpenConnections(connChannel));
			return this.closeClientChannel(connChannel);
		}
		connChannel.configureBlocking(false);

		// Register the channel to read data
		connChannel.register(this.selector, SelectionKey.OP_READ,
			new StringBuffer(maxRequestSize));
		this.addConnection(server, connChannel);
		this.processAcceptedConnection(connChannel);
		log.info("Processed accepted connection " + connChannel + "; " +
				"numOpenConnections=" + numOpenConnections(connChannel));
		return true;
	}

	private boolean isAcceptable(SocketChannel channel) {
		InetAddress ip = channel.socket().getInetAddress();
		if (this.clientToConnections.containsKey(ip)) {
			Set<SocketChannel> connections = this.clientToConnections.get(ip);
			assert (connections != null);
			if (connections != null && connections.size() > maxConnectionsPerIP)
				return false;
		}
		return true;
	}
	private int numOpenConnections(SocketChannel channel) {
		InetAddress ip = channel.socket().getInetAddress();
		if (this.clientToConnections.containsKey(ip)) {
			Set<SocketChannel> connections = this.clientToConnections.get(ip);
			assert (connections != null);
			return connections.size();
		}
		return 0;
	}

	private boolean addConnection(ServerSocketChannel server, SocketChannel conn) {
		Set<SocketChannel> connChannels =
				(this.serverToConnections.containsKey(server) ? this.serverToConnections.get(server)
						: new HashSet<SocketChannel>());
		boolean added = connChannels.add(conn);
		this.serverToConnections.put(server, connChannels);
		return added;
	}

	private boolean removeConnection(SocketChannel conn) {
		Entry<ServerSocketChannel, Set<SocketChannel>> entry = null;
		for (Iterator<Entry<ServerSocketChannel, Set<SocketChannel>>> iter =
				this.serverToConnections.entrySet().iterator(); iter.hasNext();) {
			if ((entry = iter.next()) != null && entry.getValue() != null &&
					entry.getValue().remove(conn)) return true;
		}
		return false;
	}

	private static Set<Integer> getSingleIntegerSet(int element) {
		Set<Integer> set = new HashSet<Integer>();
		set.add(element);
		return set;
	}

	public static void main(String[] args) {
		String regex = "(.*" + Config.DEFAULT_REQUEST_SPLITTER_REGEX + ")+";
		System.out.println(regex);
		System.out.println("GET test.jpg 1\nGET test.jpg 2\n".matches(regex));
	}
}
