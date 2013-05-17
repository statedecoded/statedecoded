from lxml import etree


if __name__ == "__main__":
    from sys import argv
    # Apply xslt file at argv[2] to xml file at argv[1]
    xmlFile = etree.parse(open(argv[1]))
    xslt = etree.parse(open(argv[2]))
    transform = etree.XSLT(xslt)
    result = transform(xmlFile)
    print result
