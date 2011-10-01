<?xml version="1.0"?>
<refentry id="{@id}">
    <refnamediv>
        <refname>Approaches with Client</refname>
        <refpurpose>Description of the various approaches in using the Client class</refpurpose>
    </refnamediv>
    <refsynopsisdiv>
        <author>Vasil Rangelov
            <authorblurb>
                {@link mailto:boen.robot@gmail.com boen.robot@gmail.com}
            </authorblurb>
        </author>
    </refsynopsisdiv>
    {@toc}
    <refsect1 id="{@id sync-request}">
        <title>Synchonious requests</title>
        <para>
The easiest approach in using PEAR2_Net_RouterOS is to connect, send a request, get the responses, and use them if you need to, all at one time. This is reffered to as "Synchonious request".
        </para>
        <refsect2 id="{@id sync-request-simple}">
            <title>Simple requests</title>
            <para>
If the request you want to send is just a simple command with no arguments, the easiest way is to pass it right there at the {@link Client::sendSync()} method, like this:
            </para>
            {@example sync-request-simple.php}
            <para>
You can also use the syntax from RouterOS's shell (spaces between words instead of "/"), but again - no arguments. Also, the command needs to be absolute (begin with "/"). Examples in the rest of this documentation will use the API syntax.
            </para>
        </refsect2>
        <refsect2 id="{@id sync-request-arguments}">
            <title>Requests with arguments</title>
            <para>
To add arguments to a command, you need to use the {@link Request::setArgument()} method before you send the request. You can reuse the same request object by clearing its arguments and/or setting new values appropriately, as in the following example.
            </para>
            {@example sync-request-arguments.php}
        </refsect2>
    </refsect1>
    <refsect1 id="{@id async-request}">
        <title>Asynchronous requests</title>
        <para>
You may want to deal with the responses from commands later instead of right after you send them.
Or you might only need to deal with one of the responses, and yet you need to send several requests. Or you might want to use a command which returns responses continiously, and is therefore not suitable for {@link Client::sendSync()}.
Either way, {@link Client::sendAsync()} is the method you need. Depending on the way you want to deal with the responses, there are various other methods which you may use along with it.
        </para>
        <refsect2 id="{@id send-and-forget}">
            <title>Send and forget</title>
            <para>
If you don't care about the responses, you can just do something like the following
            </para>
            {@example send-and-forget.php}
            <para>
Note that, as in the example above, different asynchronious requests need to have a different "tag", regardless of whether you care about the responses or not. A "tag" in this context is a RouterOS API specific construct that allows clients like PEAR2_Net_RouterOS to keep track of responses coming from multiple requests, since they don't appear in the order of their execution. You can only reuse a tag once you get its final response.
            </para>
        </refsect2>
        <refsect2 id="{@id loop-and-extract}">
            <title>Loop and extract</title>
            <para>
One way to get responses is to let PEAR2_Net_RouterOS process any new ones, and then extract those that interest you. You can start processing with the {@link Client::loop()} method.
If you've made requests that you know will eventually be finished, you can use {@link Client::loop()} without an argument to let processing stop only once all requests have returned their final response. Here's an example that continues from the previous one.
            </para>
            {@example loop-and-extract.php}
        </refsect2>
        <refsect2 id="{@id callback-and-loop}">
            <title>Callback and loop</title>
            <para>
Instead of extracting responses, you may instead assign responses for a request to a callback. Once you do that, starting the processing is all you need to do.
            </para>
            {@example callback-and-loop.php}
        </refsect2>
        <refsect2 id="{@id callback-and-loop}">
            <title>Send and complete</title>
            <para>
Processing of responses can also be started with {@link Client::completeRequest()}. The difference is that {@link Client::loop()} ends when a certain timeout is reached, or when all requests are finished, and {@link Client::completeRequest()} instead ends when the final response of a specified request has been processed, regardless of the time it takes. The return value is an array of all responses, or an empty array if the request was assigned to a callback.
            </para>
            {@example send-and-complete.php}
        </refsect2>
    </refsect1>
</refentry>