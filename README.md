# The State Decoded

## What is The State Decoded?
The State Decoded is a free, open source, web-based application to display laws online. Although it's meant for laws, it'll basically work for any structured legal text. It makes legal text searchable, interconnected, and machine-readable, adding an API, bulk downloads, and powerful semantic analysis tools. With The State Decoded, legal codes become vastly easier to read, more useful, and more open. Here's an actual before-and-after from the Code of Virginia:

![Before and After](https://s3.amazonaws.com/statedecoded.com/comparison.jpg)

## Can I try it out?
Sure! This project can be seen in action on the site for [Virginia](https://vacode.org/). If you want to install it, you can [download and run it in Docker](https://github.com/statedecoded/statedecoded/releases).

### Running locally with Docker

**Start:**

```bash
cp .env.example .env   # first time only; defaults work out of the box
./deploy/docker-run.sh
```

This builds the PHP 8 / Apache image, starts a MySQL 8 database, and serves the site at `http://localhost:8080/`. The admin panel is at `http://localhost:8080/admin/` (username `admin`, password `admin` — both configurable in `.env`).

The site will be empty until you import a legal code. See the [import documentation](https://docs.statedecoded.com/parser.html) for instructions, then use the admin panel or run `docker compose -f deploy/docker-compose.yml exec app php statedecoded parse` to kick off the parser.

**Stop:**

```bash
./deploy/docker-stop.sh                     # stops containers; database is preserved
docker compose -f deploy/docker-compose.yml down -v  # stops containers and wipes the database
```

**Run tests:**
```bash
./docker-test.sh
```

**Optional: Memcached**

```bash
docker compose -f deploy/docker-compose.yml --profile cache up -d
# Add CACHE_HOST=memcached and CACHE_PORT=11211 to .env and restart the app.
```

## Is this ready for prime time?
The 1.0 release was used in production on a half-dozen different sites, with [no serious bugs](https://github.com/statedecoded/statedecoded/issues?direction=desc&labels=Bug&milestone=2&state=open), and is certainly in good enough shape to be used on websites that aren't official, government-run repositories of the law.

## How do get my legal code into The State Decoded?
There are two ways.

1. Natively, The State Decoded imports XML in [The State Decoded XML format](http://docs.statedecoded.com/xml-format.html). If you have your legal code as XML, you can adapt [the provided XSLT](https://github.com/statedecoded/statedecoded/blob/master/sample.xsl) to transform it into the proper format. Or if you don't have your legal code as XML, you can convert it into XML.
2. Skip XML entirely and [modify the included parser](https://docs.statedecoded.com/parser.html) to import it in the format in which you have it.

## Project documentation
Project documentation can be found at [docs.statedecoded.com](https//docs.statedecoded.com/), which explains how to install the software, configure it, customize it, use the API, and more. The documentation is stored [as a GitHub project](https://github.com/statedecoded/documentation/), with its content automatically published via [Jekyll](https://jekyllrb.com/), so in addition to reading the documentation, you are welcome to make improvements to it!

## How to help
* Use State Decoded sites and share your feedback in the form of [filing issues](https://github.com/statedecoded/statedecoded/issues/new)—suggestions for new features, notifications of bugs, etc.
* Write or edit documentation on [the wiki](https://github.com/statedecoded/statedecoded/wiki).
* Read through [unresolved issues](https://github.com/statedecoded/statedecoded/issues) and comment on those on which you have something to add, to help resolve them.
* Contribute code to [fix bugs or add features](https://github.com/statedecoded/statedecoded/issues).
* Comb through [existing code](https://github.com/statedecoded/statedecoded) to clean it up—standardizing code formatting, adding docblocks, or editing/adding comments.

## Supported by
Development of The State Decoded was funded by [the John S. and James L. Knight Foundation’s News Challenge](https://www.knightfoundation.org/grants/20110158/).
