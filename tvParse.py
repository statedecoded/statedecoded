import json
import requests


def _zipList(tvEntry):
    tupled = zip(tvEntry[0::2], tvEntry[1::2])
    return dict(tupled)


class TermDictionary(object):
    def __init__(self):
        super(TermDictionary, self).__init__()
        # terms -> column id
        # columnId -> term
        self.termToCol = {}
        self.colToTerm = {}
        self.counter = 0

    def addTerms(self, terms):
        for term in terms:
            if term not in self.termToCol:
                self.termToCol[term] = self.counter
                self.colToTerm[self.counter] = term
                self.counter += 1

    def __str__(self):
        assert len(self.termToCol) == len(self.colToTerm)
        return str(len(self.termToCol)) + ": " + repr(self.termToCol)


class TermVector(object):
    @staticmethod
    def __zipTermComponents(definitionField):
        return {key: _zipList(value)
                for key, value
                in _zipList(definitionField).iteritems()}

    def __init__(self, tvFromSolr):
        super(TermVector, self).__init__()
        zipped = _zipList(tvFromSolr)
        self.uniqueKey = zipped['uniqueKey']
        self.termVector = self.__zipTermComponents(zipped['definition'])


class TermVectorCollection(object):
    def __init__(self, solrResp):
        super(TermVectorCollection, self).__init__()
        termVectors = solrResp['termVectors']
        self.termDict = TermDictionary()
        self.tvs = {}
        for tv in termVectors:
            if "uniqueKey" in tv and isinstance(tv, list):
                parsedTv = TermVector(tv)
                self.tvs[parsedTv.uniqueKey] = parsedTv
                self.termDict.addTerms(parsedTv.termVector.keys())
        print self.termDict


class TermVectorCollector(object):
    """ Query a batch of term vectors for a given field
        using 'id' as the uniqueKey """

    def __pathToTvrh(self, solrUrl, collection):
        import urlparse
        userSpecifiedUrl = urlparse.urlsplit(solrUrl)
        schemeAndNetloc = urlparse.SplitResult(scheme=userSpecifiedUrl.scheme,
                                               netloc=userSpecifiedUrl.netloc,
                                               path='',
                                               query='',
                                               fragment='')
        print schemeAndNetloc
        solrBaseUrl = urlparse.urlunsplit(schemeAndNetloc)
        solrBaseUrl = urlparse.urljoin(solrBaseUrl, 'solr/')
        solrBaseUrl = urlparse.urljoin(solrBaseUrl, collection + '/')
        solrBaseUrl = urlparse.urljoin(solrBaseUrl, 'tvrh')
        return solrBaseUrl

    def __init__(self, solrUrl, collection, tvField):
        super(TermVectorCollector, self).__init__()
        self.solrTvrhUrl = self.__pathToTvrh(solrUrl, collection)
        self.tvField = tvField

    def collect(self, start, rows):
        params = {"tv.fl": self.tvField,
                  "fl": "id",
                  "wt": "json",
                  "indent": "true",
                  "tv.all": "true",
                  "rows": rows,
                  "start": start,
                  "q": self.tvField + ":[* TO *]"}
        resp = requests.get(url=self.solrTvrhUrl,
                            params=params)
        print TermVectorCollection(resp.json)
        pass


if __name__ == "__main__":
    from sys import argv
    #respJson = json.loads(open('tvTest.json').read())
    #tvResp = TermVector(respJson)
    tvc = TermVectorCollector(argv[1], argv[2], argv[3])
    tvc.collect(0, 1000)
    print "Done"
