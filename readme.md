
# A Rich Socrata API wrapper

A wrapper around the [API](http://dev.socrata.com/docs/endpoints.html) provided by [Socrata](http://www.socrata.com/).

[Other libraries are available](http://dev.socrata.com/libraries/) in a variety of languages.

While this wrapper is helpful, some knowledge of [Authentication](http://dev.socrata.com/docs/authentication.html),
[Row Identifiers](http://dev.socrata.com/docs/row-identifiers.html) & [SoQL Queries](http://dev.socrata.com/docs/queries.html) will be required.
You'll probably want to also understand the difference between [Upserting](http://dev.socrata.com/publishers/upsert.html) & [Replacing](http://dev.socrata.com/publishers/replace.html).

## Requirements

\>= PHP 5.4.0

## Disclaimer

This project is currently a work in progress. Create your own fork if you plan to rely on it heavily.

## Installation

### Via Composer

Add the following to your composer.json `require` & `repositories`

```json
"require": {
    "bathhacked/rich-socrata-wrapper": "dev-master@dev"
},
"repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:BathHacked/rich-socrata-wrapper.git"
    }
]
```

The only dependency is [Guzzle](https://github.com/guzzle/guzzle).

## Connecting To Socrata

To do anything with your datastore you'll need a connection to it.

Without authentication you can find & read from public datastores.

```php
$socrata = new \BathHacked\Socrata('[url-of-your-datastore]');
```

To read your private datasets or update any of your datasets, you'll need an app token, username & password.

```php
$socrata = new \BathHacked\Socrata(
    '[url-of-your-datastore]',
    '[your-app-token]',
    '[your-username]',
    '[your-password]
);
```

## Raw Socrata Operations

Now you have a connection, you can `GET`, `POST`, `PUT` & `DELETE` to it.

```php
$response = $socrata->get('/path-to-something', ['queryParam1'=>'value1']);

$payload = ['payload1'=>'value1', 'payload2'=>'value2'];

$response = $socrata->post('/path-to-something', $payload, ['queryParam1'=>'value1']);

$response = $socrata->put('/path-to-something', $payload, ['queryParam1'=>'value1']);

$response = $socrata->delete('/path-to-something', ['queryParam1'=>'value1']);
```

See the Socrata documentation for more information about the parameters & the difference between `POST` & `PUT` (upsert & replace).

The Socrata API limits the number of rows that can be returned by a `GET` operation to 1000 (what we call chunks).
You can lower this value but never increase it above `\BathHacked\Socrata::ABSOLUTE_MAXIMUM_CHUNK_SIZE = 1000`.

```php
$socrata->setMaximumChunkSize(200);

$maximumChunkSize = $socrata->getMaximumChunkSize();
```

If you want to be sure that you don't make a mess of your beautiful data,
setting the connection to read-only will prevent any `POST` or `PUT` operations.

```php
$socrata->readOnly();

$socrata->readOnly(false); // Make it writable again
```

The API employs a hidden identifier `:id` which you can get. This is useful for dealing with anonymous rows.

```php
$internalId = $socrata->getInternalId();
```

You can generate a basic hash (sha1 of base URL) for the connection e.g. to use in caching

```php
$hash = $socrata->getConnectionHash();
```

Finally, you can start doing things to a dataset/resource (`\BathHacked\Resource`), using the Socrata "4-4" ID

```php
$resource = $socrata->resource('abcd-efgh');
```

## Adding Data To Your Resource

Now you can add something to your resource. Let's replace our entire resource by passing an array of rows to `replace()`.

```php
$sampleData = [
    [
        'id'       => 1,
        'numeric'  => 11,
        'text'     => 'text1',
    ],
    [
        'id'       => 2,
        'numeric'  => 22,
        'text'     => 'text2',
    ],
    [
        'id'       => 3,
        'numeric'  => 33,
        'text'     => 'text3',
    ],
    [
        'id'       => 4,
        'numeric'  => 44,
        'text'     => 'text4',
    ],
];

$resource->replace($sampleData);

```

We can now update this information using `upsert()`.

```php
$resource->upsert([
    ['id'=>2,'text'=>'foobar']
]);
```

__For `upsert` to work you must set a row identifier on your dataset & include the identifier with your data.
If you don't do this `upsert` will append the data to your dataset.__

You can set your row identifier by going to `About > Edit Metadata > API Endpoint > Row Identifier` within your dataset.

__If your dataset doesn't have a unique identifier, we strongly recommend using a unique row identifier which can be regenerated from the rest of the row, rather than
a simple monotonic integer sequence or relying on the internal identifier.__

When data is sent to the connection it is sent in chunks. The chunk size defaults to the maximum chunk size set for the connection
but can be specified using `setChunkSize()`.
The size cannot exceed the maximum for the connection.

`replace()` & `upsert()` take an optional extra parameter. This is a `Closure` which is invoked after each chunk
has been sent to the connection, with the number of rows sent so far & the result of the operation.

```php
$resource
    ->setChunkSize(2)
    ->upsert([
        ['id'=>1,'text'=>'foobar1'],
        ['id'=>2,'text'=>'foobar2'],
        ['id'=>3,'text'=>'foobar3'],
        ['id'=>4,'text'=>'foobar4'],
    ], function($sent, $result){
        var_dump($sent, $result);
    });

int(2)
array(6) {
  ["By RowIdentifier"]=>
  int(2)
  ["Rows Updated"]=>
  int(2)
  ["Rows Deleted"]=>
  int(0)
  ["Rows Created"]=>
  int(0)
  ["Errors"]=>
  int(0)
  ["By SID"]=>
  int(0)
}
int(4)
array(6) {
  ["By RowIdentifier"]=>
  int(2)
  ["Rows Updated"]=>
  int(2)
  ["Rows Deleted"]=>
  int(0)
  ["Rows Created"]=>
  int(0)
  ["Errors"]=>
  int(0)
  ["By SID"]=>
  int(0)
}
```

## Querying A Resource

Let's start again with an example.

```php
$resource->replace($sampleData); // Reset our contents

$rows = $resource
    ->select(['id', 'numeric', 'text'])
    ->where('numeric', '>', 11)
    ->orderBy('text', 'DESC')
    ->offset(1)
    ->limit(2)
    ->get();

var_dump($rows);

array(2) {
  [0]=>
  array(3) {
    ["id"]=>
    string(1) "3"
    ["text"]=>
    string(5) "text3"
    ["numeric"]=>
    string(2) "33"
  }
  [1]=>
  array(3) {
    ["id"]=>
    string(1) "2"
    ["text"]=>
    string(5) "text2"
    ["numeric"]=>
    string(2) "22"
  }
}
```

A closer look at the methods :-

`select(array $fieldSpecs)`: Select which fields or expressions we want to return for each row.

`addSelect($fieldSpec)`: Add another field to select.

`whereRaw($clause)`: Add a raw where clause. This allows you to construct more complex where clauses.

`where($field, $operator, $value)`: A helper which escapes `$value` with single quotes.

`whereEquals($field, $value)`: Another helper for when we're looking for equality.

__All where clauses that are added are ANDed together. Use `whereRaw` to perform ORing.__

__Make sure literal values, in where clauses, are surrounded by single quotes__

`orderBy($field, $direction = 'ASC')`: Order by a field, either `ASC`ending or `DESC`ending.

`groupBy($field)`: Group by a field.

`textSearch($query)`: Perform a full text search across all fields.

`limit($limit)`: Limit the the number of rows. This cannot exceed the connection's chunk size.

`offset($offset)`: Zero based offset within the rows.

`resetQuery()`: Reset the query.

`toParams()`: Will give you the query parameters that would be passed to the `GET` request.

## Retrieving Rows

As we saw in the previous example, the most simple way to retrieve rows is to use `get()`.
This respects the `offset` & `limit` applied.

If we want to get all rows which match the query, regardless of `offset` & `limit`, we can use `all()`.

```php
$rows = $resource->all();
```

Under the hood, `all()` will retrieve all of the results as a series of `getChunkSize()` chunks, so you can
stop worrying about the `GET` limit.

To preserve sanity & to prevent memory limit problems, `all()` bails out by default after `\BathHacked\Resource::BAILOUT = 100000` rows. You can
override this by providing a number or `\BathHacked\Resource::FETCH_ALL`.

```php
$rows = $resource->all(\BathHacked\Resource::FETCH_ALL);
```

## Iterating Rows

If you need to iterate over all rows matching a query (ignoring `limit` & `offset`) you can use `iterateChunks()` & `iterateRows()`.

Each takes a `Closure` which is called for each chunk or row.

Let's copy from one resource to the another & increase the `numeric` field by one.

```php
$source = $socrata->resource('abcd-efgh')->readOnly();

$destination = $socrata->resource('ijkl-mnop');

$source->iterateChunks(function($chunk) use($destination) {

    foreach($chunk as $index => $row)
    {
        $chunk[$index]['numeric'] += 1;
    }

    $destination->upsert($chunk);
});

$destination->iterateRows(function($row){
    var_dump($row);
});

array(3) {
  ["id"]=>
  string(1) "1"
  ["text"]=>
  string(5) "text1"
  ["numeric"]=>
  string(2) "12"
}
array(3) {
  ["id"]=>
  string(1) "2"
  ["text"]=>
  string(5) "text2"
  ["numeric"]=>
  string(2) "23"
}
array(3) {
  ["id"]=>
  string(1) "3"
  ["text"]=>
  string(5) "text3"
  ["numeric"]=>
  string(2) "34"
}
array(3) {
  ["id"]=>
  string(1) "4"
  ["text"]=>
  string(5) "text4"
  ["numeric"]=>
  string(2) "45"
}
```

We can use `getChunkSize()` & `setChunkSize()` to control the size of the chunks per resource.

## Deleting Rows

If you want to delete all items matching a query (ignoring `limit` & `offset`) you can use `delete()`.

```php
$survivors = $destination->where('numeric', '>', '30')->delete()->resetQuery()->all();

var_dump($survivors);

array(2) {
  [0]=>
  array(3) {
    ["id"]=>
    string(1) "1"
    ["text"]=>
    string(5) "text1"
    ["numeric"]=>
    string(2) "12"
  }
  [1]=>
  array(3) {
    ["id"]=>
    string(1) "2"
    ["text"]=>
    string(5) "text2"
    ["numeric"]=>
    string(2) "23"
  }
}



```

## Retrieving Metadata

Socrata provides an undocumented interface to datastore metadata. __This may be subject to change.__

```php
$metadata = $socrata->metadata();

$allMetadata = $metadata->all();

$found = $metadata->search('foobar', $allMetadata);

$resourceMetadata = $metadata->resource('abcd-efgh');

$resourceId = $resourceMetadata->getResourceId();

$name = $resourceMetadata->getName();

$description = $resourceMetadata->getDescription();

$category = $resourceMetadata->getCategory();

$tags = $resourceMetadata->getTags();

$displayType = $resourceMetadata->getDisplayType();

$columns = $resourceMetadata->getColumns();

$idColumn = $resourceMetadata->getRowIdentifierColumn();

$hasFoobar = $resourceMetadata->contains('foobar');

// We can also use magic accessors to get top level fields

$count = $resourceMetadata->downloadCount;

// Or just grab as an array or JSON

$allFields = $resourceMetadata->toArray();

$json = $resourceMetadata->toJson();
```

`search()` returns metadata for resources which contain the search expression in any of its values via `contains()`.
The optional second parameter allows you to provide an array already retrieved & defaults to calling `all()`.

## Caching Metadata

Retrieving metadata can be very slow. Caching helps a lot.

Add `"doctrine/cache": "~1.4"` to the `require` section of your `composer.json`, `composer update` & try something like the following.

```php
@require_once 'vendor/autoload.php';

$socrata = new \BathHacked\Socrata(
    'https://opendata.socrata.com'
);

$cache = new \Doctrine\Common\Cache\PhpFileCache('/path/to/cache');

$metadataStore = $socrata->metadata();

$allKey = $socrata->getConnectionHash() . '/metadata/all';

$all = $cache->fetch($allKey);

if(!$all)
{
    $all = $metadataStore->all();

    $cache->save($allKey, $all, 30 * 60);
}

$found = $metadataStore->search('top 10', $all);

foreach($found as $md)
{
    echo $md . PHP_EOL;
}
```

## Future Work

- Tests. We have no tests. We are bad.
- Improve the `where` methods.
- Adding a rich wrapper for rowsets & rows.
- Exploration of undocumented API for creating datasets & manipulating metadata.

## License

The MIT License (MIT)

Copyright (c) Bath Hacked

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.