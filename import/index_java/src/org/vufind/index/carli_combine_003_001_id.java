package org.vufind.index;

import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.Set;
import org.marc4j.marc.Record;
import org.solrmarc.index.SolrIndexer;
import org.solrmarc.tools.SolrMarcIndexerException;

//import org.apache.log4j.Logger;
public class carli_combine_003_001_id
{
//protected static Logger logger = Logger.getLogger(Utils.class.getName());

/**
* Extract the call number label from a record
* @param record
* @return Call number label
*/
public String combine_003_001_id(Record record) {
   String id = SolrIndexer.instance().getFirstFieldVal(record, "001");
   String org = SolrIndexer.instance().getFirstFieldVal(record, "003");
    if (org == null) {
       // org is pretty important!
       throw new SolrMarcIndexerException(SolrMarcIndexerException.EXIT, "Record does not contain a 003! Fatal exception. Record: " + record);
   }
   return org + "." + id;
}

public Set create_local_ids_str_mv(Record record) {
    // Initialize our return value:
    Set result = new LinkedHashSet();

    // Dummy link (which will cause the deduped record to always display by default)
    result.add("(CARLI)");

    // Loop through the specified MARC fields:
    Set input = SolrIndexer.instance().getFieldList(record, "035a");
    Iterator<String> iter = input.iterator();
    while (iter.hasNext()) {
        // Get the current string to work on:
        String current = iter.next();
        String _035a = current.toString();

        // parse out institution
        int inx1 = -1;
        int inx2 = -1;
        if ((inx1 = _035a.indexOf("(")) >= 0) {
            if ((inx2 = _035a.indexOf(")")) >= 0 && inx2 > inx1) {
                String the035a = _035a.substring(inx1+1, inx2);

                // keep only CARLI libraries
                if (is_carli_library(the035a)) {
                      result.add(_035a);
               }
            }
        }

    }

    // If we found no call numbers, return null; otherwise, return our results:
    if (result.isEmpty())
        return null;

    return result;
}

public Set create_institutions(Record record) {
    // Initialize our return value:
    Set result = new LinkedHashSet();

    // Loop through the specified MARC fields:
    Set input = SolrIndexer.instance().getFieldList(record, "035a");
    Iterator<String> iter = input.iterator();
    while (iter.hasNext()) {
        // Get the current string to work on:
        String current = iter.next();
        String _035a = current.toString();


        // parse out institution
        int inx1 = -1;
        int inx2 = -1;
        if ((inx1 = _035a.indexOf("(")) >= 0) {
            if ((inx2 = _035a.indexOf(")")) >= 0 && inx2 > inx1) {
                String the035a = _035a.substring(inx1+1, inx2);

                // keep only CARLI libraries
                if (is_carli_library(the035a)) {
                   //logger.debug("the035a = " + the035a + "\n");
                      result.add(the035a);
               }
            }
        }

    }

    // If we found no call numbers, return null; otherwise, return our results:
    if (result.isEmpty())
        return null;

    return result;
}

public static boolean is_carli_library(String inst) {
    // special case for HathiTrust
    if (inst.equals("HAT")) {
        return true;
    }
    // special case for EBL
    if (inst.equals("EBL")) {
        return true;
    }
    // special case for OTL
    if (inst.equals("OTL")) {
        return true;
    }
    // special case for OAC
    if (inst.equals("OAC")) {
        return true;
    }
    if (inst.length() == 5 && inst.endsWith("db")) {
        return true;
    }
    return false;
}

}
