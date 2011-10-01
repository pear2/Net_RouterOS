<?xml version="1.0"?>
<refentry id="{@id}">
    <refnamediv>
        <refname>Using queries</refname>
        <refpurpose>A brief guide to using queries</refpurpose>
    </refnamediv>
    <refsynopsisdiv>
        <author>Vasil Rangelov
            <authorblurb>
                {@link mailto:boen.robot@gmail.com boen.robot@gmail.com}
            </authorblurb>
        </author>
    </refsynopsisdiv>
    {@toc}
    <refsect1 id="{@id command-handling-queries}">
        <title>Commands handling queries</title>
        <para>
Queries are a RouterOS API specific construct that allows you to limit the results returned for a request.
        </para>
        <para>
Currently, the "print" command is the only one that handles queries, since version 3.21. PEAR2_Net_RouterOS doesn't check whether the command handles queries, so if future versions of RouterOS have other such commands, you can use queries with them right away.
        </para>
    </refsect1>
    <refsect1 id="{@id setting-query}">
        <title>Setting a query</title>
        <para>
To set a query for a request, you need to use the {@link Request::setQuery()} method. If later in the script you want to remove the query, you can pass NULL to it. The rest of the examples in this tutorial will assume a script similar to the following, where the $query variable is defined separately:
        </para>
        <programlisting role="php">
&lt;?php
namespace PEAR2\Net\RouterOS;
include_once 'PEAR2/Net/RouterOS/autoload.php';
$client = new Client('192.168.0.1', 'admin');

$request = new Request('/ip/arp/print');

//Define $query here

$request->setQuery($query);
$responses = $client->sendSync($request);

foreach($responses as $response) {
    foreach($response->getAllArguments() as $name => $value) {
        echo "{$name}: {$value}\n";
    }
    echo "====\n";
}
?&gt;
        </programlisting>
    </refsect1>
    <refsect1 id="{@id simple-query}">
        <title>A simple query</title>
        <para>
You can create a query by calling the static {@link Query::where()} method, along with the first criteria of the query. For example, if you wanted to limit results to the entry about 192.168.0.100, you can use a query like:
        </para>
        <programlisting role="php">$query = Query::where('address', '192.168.0.100');</programlisting>
        <para>
Using the optional third parameter, you can specify exactly what do you want to do with the value. Possible values are the <emphasis>Query::ACTION_*</emphasis> constants. For example, if you wanted to get all addresses greather than 192.168.0.100, you can use:
        </para>
        <programlisting role="php">$query = Query::where('address', '192.168.0.100', Query::ACTION_GREATHER_THAN);</programlisting>
    </refsect1>
    <refsect1 id="{@id chaining-conditions}">
        <title>Chaining conditions</title>
        <para>
The {@link Query} class uses a "fluent" interface, i.e. it always returns the query object itself, similarly to how {@link http://jquery.com jQuery} and {@link http://framework.zend.com/manual/en/zend.db.select.html Zend_Db_Select} do it. Thanks to that, you can chain conditions right when defining the $query variable (though you can also alter it later). For example, if you wanted to get all addresses greather than or equal to 192.168.0.100, you can do:
        </para>
        <programlisting role="php">$query = Query::where('address', '192.168.0.100', Query::ACTION_GREATHER_THAN)->orWhere('address', '192.168.0.100');</programlisting>
    </refsect1>
</refentry>