# Solr perspective
##searching
* normal searching: [http://localhost:8983/solr/statedecoded/search?q=automobile tax](http://localhost:8983/solr/statedecoded/search?q=automobile tax)
  * by our default settings we search for term matches over: catch_line, tags, text, structure, section_ancestor, section_descendent, refers_to_descendent, refers_to_ancestor, referred_to_by_descendent, referred_to_by_ancestor, term, definition
  * section_ancestor and section_descendent are boosted high so that if a section of law is searched for, it matches (rather than matching on a reference in the text)
  * we also search for phrase matches in: catch_line, tags, text, structure, term, definition
  * what is "catch_line"?
* searching over law sections: [http://localhost:8983/solr/statedecoded/search?q=19.2-76](http://localhost:8983/solr/statedecoded/search?q=19.2-76)
  * this has been tuned so that the law most specific to the query is discovered first followed by laws directly above or below the specified law in the hierarchy, followed by laws that refer to this law or are referred by this law. Ex: 19.2-76; 19.2-76.3; 19.2-76.2; 19.2-76.1; 19.2-129; 19.2-73.1
  * We have made it forgiving on mistakes with the law delimiter. Currently we are considering both “.” and “-” to be valid hierarchy delimiters. However we haven’t made this exposable in the setup!
* When someone selects a particular document, note it’s id field and retrieve that particular document by: q=id:l_23425
* If you want to show a single document highlighted, then perform the same query at the singledoc request handler and the text of the law or definition will come back highlighted. http://localhost:8983/solr/statedecoded/singledoc?q=automobile tax&id=l_28328
  * If you don’t specify a query (q) then the single document will come back with no highlights.
  * If you do not specify an id, then no results will be returned. 
  
##grouping
* I didn’t include grouping by default because it might just be better to issue two searches, once for laws, once for definitions. If you want to group, here’s how!
grouping take 1 (complex structure): http://localhost:8983/solr/statedecoded/search
  * ?q=whatever
  * &group=true    turn groups on
  * &group.field=type   group on type
  * &group.limit=10   make each group 10 results long
  * &sort=type desc, score desc
* grouping take 2(flat structure): http://localhost:8983/solr/statedecoded/search
  * ?q=whatever
  * &group=true
  * &group.field=type
  * &group.limit=10
  * &sort=type desc, score desc
  * &group.main=true
* You can page through groups with group.offset - though I don’t know how to page through a specific group
* More information at http://wiki.apache.org/solr/FieldCollapsing
* You might just want to issue two separate queries rather than worrying about grouping at all. Grouping saves some Solr sweat if you’re really being pounded, but I double local municipalities or even state municipalities are going to get enough traffic to create problems. ~And if they do, then there are other ways to improve performance of Solr.

##facet display
* Easiest facet display to type (Which is either law or dict.) http://localhost:8983/solr/statedecoded/search?q=whatever
  * &facet=true
  * &facet.field=type
* Facet numbers automatically change based upon the query
* If someone selects a facet, then add this to a filter query and only those documents are returned. For instance if the law facet is selected: http://localhost:8983/solr/statedecoded/search?q=whatever
  * &fq=type:law
* Tags as facet: use tags_facet for listing because tags is stemmed so that it can be searched over: http://localhost:8983/solr/statedecoded/search?q=whatever
  * &facet.field=tags_facet
  * for filtering just add &fq=tags_facet:whatever
* Displaying: section facets. It’s hard to get this right because every option has a drawback. You might simply opt not to.
  * option 1: section_facet so that the facet display is the same as when it was indexed. The problem is that the facets are not hierarchical and thus they all have a value of 1.
  * option 2: section_descendent - You get enough information to make a hierarchy, but you have to organize it yourself. Also we’ve had to replace the section delimiters with ~ and I don’t know how to reconstruct that.
  * option 3: section_facet_hierarchical - You can specifically ask for a certain depth into the the section hierarchy by specifying f.section_facet_hierarchical.facet.prefix. This makes it easier to parse and to deal with. Problem again that ~ replaces normal delimiters.
  * option 4: We could create a special analysis Java code to split on a regex (in this case “[.-]”) and prefix the tokens with the depth. But this might be complicated for users.
* Section filtering - just add fq=section:32.5   (or whatever section they are filtering over)
* Displaying: structure facets. Also a little difficult to do right - but for different reasons. The problem is that the segments of the structure hierarchy is often too long to reasonably display.
  * Option 1: structure_descendent - similar to section_descendent - you have to do the organization of the results into a facet hierarchy.
  * Option 2: structure_facet_hierarchical - and you can find facets n level deep via f.structure_facet_hierarchical.facet.prefix=1
  * Option 3: We could create a special analysis Java code to split on a regex as above.
  * Section filtering - just add fq=structure_descendent:”Conservation/Virginia Waste Management Act” and that will find any document that is a descendent of this structure.

##highlighting
* Highlighting is part of /search and /singledoc request handlers by default.
* By default, we highlight only fields definition and text. You can modify this with the hl.fl (highlight field list parameter).
* The highlights section is returned past facets in the result set. It’s a list of snippets for the specified field for each document that matches.
* By default we return 2 snippets of lenght 100 each. If we can’t do this, then we return the first 200 characters of the appropriate fields. (This reduces logic in the client in the case that a match doesn’t occur in that field.)
* We’ve surrounded the matches with <span class=”highlight”>the highlight</span>. You can make it look like whatever you want in CSS

##spell correction
* The spellcheck component is built into the search request handler. If someone misspells a word, then last section of the response will have corrections. Notably, the collation sections will have recommendations of similar multi-term searches that are guaranteed to have results. Try http://localhost:8983/solr/statedecoded/search?q=electrc%20vehilce
* I have dumped the text, tags, structure, term, and definition fields into the spelling fields, so any word present there may at some point be presented as a suggestion. Therefore it’s really a good idea not to stick comments into the spelling field ever! Otherwise you’ll inevitably get recommendations like “did you mean ‘damn communist’?”
* In order to make spell correction start working (I think) you have to build the spelling index first. Just include spellcheck.build=true in the url. Ex: http://localhost:8983/solr/statedecoded/search?q=electrc%20vehilce&spellcheck.build=true

##suggestion
* As the user is typing into the search box, you might want to provide them with suggested term completions. For this use the suggestion request handler: http://localhost:8983/solr/statedecoded/suggest?q=vehicle elec*&facet.prefix=elec
* You have to supply two things here: the partial query followed by a wildcard character, and the prefix of the term being typed. In the example here, the users is probably going to type “vehicle electricity”.
* You wanted to see a code example of this. Once Solr is running and laws are indexed, try opening statedecoded/solrdocs/demos/sample_suggest.html and you can play with a simple jquery implementation of suggestion

##more like this
* This is built into the singledoc request handler. There will be a section called moreLikeThis that contains documents considered similar. I’m currently returning several fields, so we might want to change that 
