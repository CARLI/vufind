package org.vufind.index;

import org.marc4j.marc.Record;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.*;
import java.util.List;
import java.util.ArrayList;

import org.solrmarc.tools.Utils;

//import org.apache.log4j.Logger;
//protected static Logger logger = Logger.getLogger(Utils.class.getName());

public class carli_location {

public List getLocation(Record record) {
    List locations = new ArrayList<String>();
//logger.debug("Record r = " + record + "\n");
    DataField ldf = (DataField) record.getVariableField("852");
    if (ldf != null) {
        List<Subfield> lfs = ldf.getSubfields('b');
        for (Subfield lf : lfs) {
            String location = lf.getData();
            if (location.length() > 0) {
//logger.debug("location = " + location + "\n");
                locations.add(location);
            }
        }
    }
    return locations;
}

}
