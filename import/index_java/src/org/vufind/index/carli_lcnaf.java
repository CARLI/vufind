package org.vufind.index;

import org.marc4j.marc.Record;
import org.marc4j.marc.DataField;
import org.vufind.index.UpdateDateTracker;
import org.vufind.index.LccnTools;

import org.solrmarc.index.SolrIndexer;

public class carli_lcnaf {

/**
 * Get the unique identifier for the current LCNAF record.
 * @param record
 * @return ID string
 */
public String getLCNAFID(Record record) {
    return (new LccnTools()).getFirstNormalizedLCCN(SolrIndexer.instance(), record, "010a", "lcnaf-");
}

/**
 * Determine the type of the current authority record.
 * @param record
 * @return Record type string
 */
public String getLCNAFRecordType(Record record) {
    String recordType;

    // Try to identify the record type using the main heading:
    DataField heading = null;
    if ((heading = (DataField) record.getVariableField("100")) != null) {
        recordType = "Personal Name";
    } else if ((heading = (DataField) record.getVariableField("110")) != null) {
        recordType = "Corporate Name";
    } else if ((heading = (DataField) record.getVariableField("111")) != null) {
        recordType = "Meeting Name";
    } else if ((heading = (DataField) record.getVariableField("130")) != null) {
        recordType = "Uniform Title";
    } else if ((heading = (DataField) record.getVariableField("150")) != null) {
        recordType = "Topical Term";
    } else if ((heading = (DataField) record.getVariableField("151")) != null) {
        recordType = "Geographic Name";
    } else if ((heading = (DataField) record.getVariableField("155")) != null) {
        recordType = "Heading - Genre/Form Term";
    } else if ((heading = (DataField) record.getVariableField("180")) != null) {
        recordType = "Heading - General Subdivision";
    } else if ((heading = (DataField) record.getVariableField("181")) != null) {
        recordType = "Heading - Geographic Subdivision";
    } else if ((heading = (DataField) record.getVariableField("182")) != null) {
        recordType = "Heading - Chronological Subdivision";
    } else if ((heading = (DataField) record.getVariableField("185")) != null) {
        recordType = "Heading - Form Subdivision";
    } else {
        // No recognized heading field found:
        return "Unknown";
    }

    // If we got this far, we found a main heading; let's see if it has
    // a title subfield!
    if (heading.getSubfield('t') != null) {
        recordType += " / Title";
    }

    return recordType;
}

/**
 * Get the "first indexed" date for the current record.
 * @param record
 * @return ID string
 */
public String getLCNAFFirstIndexed(Record record) {
    UpdateDateTracker tracker = UpdateDateTracker.instance();
    return tracker.getFirstIndexed();
}

/**
 * Get the "last indexed" date for the current record.
 * @param record
 * @return ID string
 */
public String getLCNAFLastIndexed(Record record) {
    UpdateDateTracker tracker = UpdateDateTracker.instance();
    return tracker.getLastIndexed();
}

}
