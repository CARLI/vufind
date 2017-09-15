package org.vufind.index;

import org.marc4j.marc.Record;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.Set;
import org.solrmarc.index.SolrIndexer;
import org.solrmarc.tools.SolrMarcIndexerException;

public class carli_collection {

public Set getCollection(Record record) {

    String org = SolrIndexer.instance().getFirstFieldVal(record, "003");
    if (org == null) {
       // org is pretty important!
       throw new SolrMarcIndexerException(SolrMarcIndexerException.EXIT, "Record does not contain a 003! Fatal exception. Record: " + record);
    }

    // Initialize our return value:
    Set result = new LinkedHashSet();

    // Loop through the specified MARC fields:
    Set input = SolrIndexer.instance().getFieldList(record, "852b");
    Iterator<String> iter = input.iterator();
    while (iter.hasNext()) {
        // Get the current string to work on:
        String current = iter.next();
        String _852b = current.toString();

        result.add(org + '_' + _852b);
    }

    // If we found no call numbers, return null; otherwise, return our results:
    if (result.isEmpty())
        return null;

    return result;
}

}
