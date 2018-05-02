package org.vufind.index;

import org.marc4j.marc.Record;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.VariableField;
import org.marc4j.marc.Subfield;

import org.solrmarc.index.SolrIndexer;
import org.solrmarc.tools.Utils;

import java.util.Set;

//import java.lang.Character;
//import java.lang.String;
import java.util.ArrayList;
import java.util.Collection;
import java.util.HashSet;
import java.util.List;
import java.util.ListIterator;
import java.util.Map;
import java.util.TreeMap;


// Give ourselves the ability to import other BeanShell scripts
//addClassPath("../import");
//importCommands("index_scripts");

// define the base level indexer so that its methods can be called from the script.
// note that the SolrIndexer code will set this value before the script methods are called.
//org.solrmarc.index.SolrIndexer indexer = null;

public class ucAuthorBrowseFunctions {

/**
 * Eventually, we want to re-code this in java, as a SolrMarcMixin class.
 */


/**
 *
 * @param record
 *
 * @param tagStr 
 *          Does not support LNK syntax
 *          If null or empty, select all subfields.
 *          Does not support character position syntax.
 *          
 * @param result
 *         <code>Collection</code> in which to collect the results. This object is returned.
 *
 * @return the <code>result</code> parameter, after populating with the desired author data.
 */
public static Collection getAuthorBrowseFields(Record record, String tagStr, Collection result)
{
    Map<String, Collection<String>> tagMap = buildTagMap(tagStr);
    // Assume authors only come from data fields
    for (DataField df : record.getDataFields()) {
String tag = df.getTag();
Collection<String> sfColl = tagMap.get(tag);
if (sfColl == null) continue;
for ( String sfSpec : sfColl ) {
    if (tag.equals("100") || tag.equals("400") || tag.equals("500") || tag.equals("700") || tag.equals("800")) {
result.add(getPersonalName(df, sfSpec));
    } else if (tag.equals("110") || tag.equals("410") || tag.equals("510") || tag.equals("710") || tag.equals("810")) {
result.add(getCorporateName(df, sfSpec));
    } else if (tag.equals("111") || tag.equals("411") || tag.equals("511") || tag.equals("711") || tag.equals("811")) {
result.add(getMeetingName(df, sfSpec));
    } else {
result.add(getSubfields(df, sfSpec));
    }
}
    }
    return result;
}

/**
 *
 * @param record
 * @param tagStr 
 *          Does not support LNK syntax
 *          If null or empty, select all subfields.
 *          Does not support character position syntax.
 *          
 * @param result
 */
public static Set getAuthorBrowseFieldsAsSet(Record record, String tagStr)
{
    return (Set) getAuthorBrowseFields(record, tagStr, new HashSet());
}

/**
 *
 * @param record
 * @param tagStr 
 *          Does not support LNK syntax
 *          If null or empty, select all subfields.
 *          Does not support character position syntax.
 *          
 * @param result
 */
public static List getAuthorBrowseFieldsAsList(Record record, String tagStr)
{
    return (List) getAuthorBrowseFields(record, tagStr, new ArrayList());
}

/*
 * Build up a data structure that we can use to pull fields while traversing a record.
 *
 * @param tagStr  colon-separated list of tag descriptions.
 *
 * @return
 */
public static Map<String, Collection<String>> buildTagMap(String tagStr)
{
    // Build up Map for tag description
    //Map tagMap = new TreeMap(); // TODO: convert to Java as Map<String, Collection>
    Map<String,Collection<String>> tagMap = new TreeMap<String, Collection<String>>();
    String[] tags = tagStr.split(":");
    //Set result = new LinkedHashSet();
    for (int i = 0; i < tags.length; i++)
    {
        // Check to ensure tag length is at least 3 characters
        if (tags[i].length() < 3)
        {
            System.err.println("Invalid tag specified: " + tags[i]);
            continue;
        }

        // Get Field Tag

        String tag = tags[i].substring(0, 3); 
        int subIndex = 3;

        boolean linkedField = false;
        if (tag.equals("LNK"))
        {
            tag = tags[i].substring(0, 6);
            linkedField = true;
            subIndex = 6;
        }
        String subfield = tags[i].substring(subIndex);

Collection<String> val;
if (tagMap.containsKey(tag)) {
    val = tagMap.get(tag);
} else {
    val = new ArrayList<String>();
}
val.add(subfield);
tagMap.put(tag, val);
   }

    return tagMap;
}

public static String getPersonalName(DataField field, String sfList)
{
    //System.out.println("Input: '" + field.toString() + "'");

    String trimChars = ".,; ";

    StringBuilder buf = new StringBuilder();
    List<Subfield> subFlds = field.getSubfields();
    for (Subfield sf : subFlds) {
char sfCode = sf.getCode();
if (sfList == null || sfList.length() == 0 || sfList.indexOf(sfCode) > -1 ) {
    if (sfCode == 'd') {
//System.out.println("Before: '" + buf.toString() + "'");
// force comma+space before subfield $d (Not period)
trimBuffer(buf, trimChars);
buf.append(", ");
String sfd = sf.getData();
// remove any parentheses from $d (these occur in non-LC headings)
if (sfd.indexOf("(") != -1 || sfd.indexOf(")") != -1 ) {
    StringBuilder dBuf = new StringBuilder(sfd);
    // assume no multiple parens
    int lpInd = sfd.indexOf("(");
    if (lpInd != -1) dBuf.deleteCharAt(lpInd);
    sfd = dBuf.toString();
                    int rpInd = sfd.indexOf(")");
    if (rpInd != -1) dBuf.deleteCharAt(rpInd);
    sfd = dBuf.toString();
}
buf.append(sfd);
//System.out.println("After:  '" + buf.toString() + "'");
    } else if ("fklst".indexOf(sfCode) != -1) {
// force period+space before subfield $f, $k, $l, $s, $t
trimBuffer(buf, trimChars);
buf.append(". ");
buf.append(sf.getData());
    } else if (sfCode=='m' || sfCode=='r') {
// force comma+space before $m, $r
trimBuffer(buf, trimChars);
buf.append(", ");
buf.append(sf.getData());
    } else {
// force exactly one space
trimBuffer(buf, " ");
buf.append(" ");
buf.append(sf.getData());
    }
}
    }
    rightTrimBuffer(buf, " ");
    trimBuffer(buf, trimChars);

    //System.out.println("Output: '" + buf.toString() + "'");
    return buf.toString();
}

public static String getCorporateName(DataField field, String sfList)
{
    String trimChars = ".,; ";

    StringBuilder buf = new StringBuilder();
    List<Subfield> subFlds = field.getSubfields();
    for (Subfield sf : subFlds) {
char sfCode = sf.getCode();
if (sfList == null || sfList.length() == 0 || sfList.indexOf(sfCode) > -1 ) {
    if ("bfklst".indexOf(sfCode) != -1) {
// force period+space before subfield $b, $f, $k, $l, $s, $t
trimBuffer(buf, trimChars);
buf.append(". ");
    } else {
// force exactly one space
trimBuffer(buf, " ");
buf.append(" ");
    }
    buf.append(sf.getData());
}
    }
    rightTrimBuffer(buf, " ");
    trimBuffer(buf, trimChars);
    return buf.toString();
}

public static String getMeetingName(DataField field, String sfList)
{
    String trimChars = ".,; ";

    StringBuilder buf = new StringBuilder();
    List<Subfield> subFlds = field.getSubfields();
    for (Subfield sf : subFlds) {
char sfCode = sf.getCode();
if (sfList == null || sfList.length() == 0 || sfList.indexOf(sfCode) > -1 ) {
    if ("efklst".indexOf(sfCode) != -1) {
// force period+space before subfield $e, $f, $k, $l, $s, $t
trimBuffer(buf, trimChars);
buf.append(". ");
    } else {
// force exactly one space
trimBuffer(buf, " ");
buf.append(" ");
    }
    buf.append(sf.getData());
}
    }
    trimBuffer(buf, trimChars);
    return buf.toString();
}


public static String getSubfields(DataField field, String sfList)
{
    String trimChars = ".,; ";

    StringBuilder buf = new StringBuilder();
    List<Subfield> subFlds = field.getSubfields();
    for (Subfield sf : subFlds) {
char sfCode = sf.getCode();
if (sfList == null || sfList.length() == 0 || sfList.indexOf(sfCode) != -1 ) {
    trimBuffer(buf, " ");
    buf.append(sf.getData());
}
    }
    rightTrimBuffer(buf, " ");
    trimBuffer(buf, trimChars);
    return buf.toString();
}

/**
 * Trims trailing characters from <code>buf</code>.
 *
 * @param buf       Buffer which is trimmed in-place.
 * @param trimChars Characters to trim from <code>buf</code>
 */
public static void trimBuffer(StringBuilder buf, String trimChars) {
    int i = buf.length()-1;
    while (i > -1 && trimChars.indexOf(buf.charAt(i)) > -1) {
buf.deleteCharAt(i);
i--;
    }
    return;
}

/**
 * Trims leading characters from <code>buf</code>.
 *
 * @param buf       Buffer which is trimmed in-place.
 * @param trimChars Characters to trim from <code>buf</code>
 */
public static void rightTrimBuffer(StringBuilder buf, String trimChars) {
    int i = buf.length();
    while (i > 0 && trimChars.indexOf(buf.charAt(0)) > -1) {
buf.deleteCharAt(0);
i--;
    }
    return;
}

// Debugging utils
public static void examineObject(Object obj) {
    System.err.println("Examining " + obj.getClass().getName());
    java.lang.reflect.Method[] methods = obj.getClass().getMethods();
    if (methods.length > 0) {
System.err.println("Methods:");
for (int i=0; i < methods.length; i++) {
    System.err.println("\t" + methods[i].toGenericString());
}
    }
}


}

/*
  Local variables:
  mode: java
  End:
 */
