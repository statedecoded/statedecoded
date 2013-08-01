<?xml version="1.0" encoding="UTF-8" ?>

<!--
	Sample State Decoded Extensible Stylesheet Language Transformation
	
	Takes Florida Statutes XML <http://www.sunshinestatutes.com/downloads/>, as provided by the
	state legislature, and transforms them into The State Decoded's XML format. This is intended as
	a sample for others to modify, to transform their own legal code's XML into The State Decoded's
	format.
	
	Created by Josh Brown, LightCastle Technical Consulting.
	Released under the GNU Public License v3.0.
-->

<!--You'll notice farther down in this XSLT some of the XPaths follow patterns of
	"orig:Chapter/orig:TitleNumber". This tells the XSLT processor to look for the XPath of
	Chapter/TitleNumber in the document that is namespaced to "orig". If you have no namespace in
	the original document, remove the xmlns:orig= line in this stylesheet and all the "orig:"
	prefixes to XPaths. -->
			
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:orig="http://StatRev.xsd">

	<xsl:preserve-space elements="*"/>
	
	<xsl:output
			method="xml"
			version="1.0"
			encoding="utf-8"
			omit-xml-declaration="no"
			indent="yes"
			media-type="text/xml"/>

	<!--Start processing at the top-level element. This will match on the root tag in the input
		document. (The first tag that isn't "<xml>" is the root tag.) For example, if the match is
		set for the fourth tag in the document, nothing in the second or third tags would make it
		into the output document. You should almost always match on the root element.
	-->
	<xsl:template match="/">

		<!--Set a variable called "docpath" so we only have to perform a single concat. This
			variable lets the XSLT find the name of the file in the next directory up that it needs
			to open. The substring-before() function remove values from the first parameter that
			occur in the string before the second parameter. So if "orig:Section/@Number" is
			"0001.01", and the second parameter is ".", substring-before will return the value
			"0001". So in effect, this is how the XSLT processor knows which file to open:
			"0001.xml" in the directory above the one where the instant XSLT file is located. -->
		<xsl:variable name="docpath" select="document(concat('../', substring-before(orig:Section/@Number, '.'), '.xml'))"/>

		<!--Begin to output the text of this law. -->
		<law>

			<structure>
				 <xsl:apply-templates select="$docpath/orig:Chapter/orig:TitleName"/>
				 <xsl:apply-templates select="$docpath/orig:Chapter/orig:ChapterName"/>
			</structure>
			
			
			<!--"select='@Number'" tells the XSLT to use the value of the "Number" property on the
				tag that it locates. Because this is follows "value-of select='orig:Section'", it
				finds the Number property of the <Section> tag of the source XML document. -->

			<section_number><xsl:value-of select="orig:Section/@Number"/></section_number>

			<!--Replace "<xsl:value-of select="orig:Section/orig:Catchline"/>" with whatever is
				the contents of the <Catchline> tag in the source XML. -->
			<catch_line><xsl:value-of select="orig:Section/orig:Catchline"/></catch_line>

			<text>
				<!--This will apply all templates that match children nodes of orig:Section in the
					sub-directory Sections/ -->
				<xsl:apply-templates/>
			</text>
			
			<!--Each time the XSLT processor encounters an orig:Section tag in the top-level
				source document, it opens up the corresponding Sections flie and sets the
				<history> tag to the corresponding <History> tag. -->
			<history><xsl:value-of select="orig:Section/orig:History"/></history>

		</law>
		
	</xsl:template>



	<xsl:template match="orig:TitleName" name="TitleName">
			
				<!--The <xsl:attribute> tag sets an attribute on the tag that it sits inside, in
					this case <unit>. The attribute will be titled whatever is in the "name="
					section, and the value will be whatever is inside the <xsl:attribute> tags. In
					this case, this will set attributes of "identifier=" "order_by=" and "level=" on
					the <unit> tag. Values for those, respectively, will be the title number from
					the input XML doc, the <order_by> tag and the number 1. The statement
					<xsl:value-of select="."/> will set the value of the <unit> tag to be whatever
					the value of the <TitleName> tag is from the original document, because a dot
					(.) is the XPath shorthand for the current tag. Because this template matches on
					"orig:TitleName", that makes that tag the current tag. -->
				<unit label="title">
						<xsl:attribute name="identifier"><xsl:value-of select="../@TitleNumber"/></xsl:attribute>
						<xsl:attribute name="order_by"><xsl:value-of select="../@TitleNumber"/></xsl:attribute>
						<xsl:attribute name="level">1</xsl:attribute>
						<xsl:value-of select="."/>
				</unit>

	</xsl:template>

	<xsl:template match="orig:ChapterName" name="ChapterName">
				<unit label="chapter">
					<xsl:attribute name="identifier"><xsl:value-of select="../orig:ChapterNumber"/></xsl:attribute>
					<xsl:attribute name="order_by"><xsl:value-of select="@Number"/></xsl:attribute>
					<xsl:attribute name="level">2</xsl:attribute>
					<xsl:value-of select = "."/>
				</unit>
	</xsl:template>

	<xsl:template match="orig:SectionBody">
		<section>
		<xsl:apply-templates/></section>
	</xsl:template>

	<xsl:template match="orig:Text">
		<xsl:apply-templates/>
	</xsl:template>

	<xsl:template match="orig:Catchline"/>

	<xsl:template match="orig:Subsection">
		<section>
		<xsl:attribute name = "prefix"><xsl:value-of select="@Id"/></xsl:attribute>
		<xsl:apply-templates/></section>
	</xsl:template>

	<xsl:template match="orig:Paragraph">
		<section>
		<xsl:attribute name="prefix"><xsl:value-of select="@Id"/></xsl:attribute>
		<xsl:apply-templates/></section>
	</xsl:template>

	<!--We're making an empty history template so that the XSLT will remove values for this tag in
		the source document. We've already told the XSLT processor earlier in this document what we
		want to do with this History tag.  -->
	<xsl:template match="orig:History">
	</xsl:template>
	
	<!--The Metadata template is unused for the example XML, but may be used for other legal codes. -->
	<xsl:template match="orig:Metadata">
		<metadata></metadata>
	</xsl:template>

	<!--The Tags template is unused for the example XML, but may be used for other legal codes. -->
	<xsl:template match="orig:Tags">
		<tags>
			<tag></tag>
		</tags>
	</xsl:template>

	<xsl:template match="orig:Order">
		<order_by></order_by>
	</xsl:template>

	<!-- This template will match on any child node of <Note> in a document. In Florida's XML (e.g.,
		0001.01.xml) there is a <Note><Text>[text]</Text></Note> section. Because we have a template
		that matches on nodes titled "<Text>", this value was being put into the transformed XML
		document. We don't want this to happen, because our "<text>" field means something very
		different than the source XML's "<Text>" field. This template handles that by selecting any
		children nodes of <Note> and doing nothing with the values found there, which functionally
		deletes the information from the transformed document. -->
	<xsl:template match="orig:Note/*"></xsl:template>
	
</xsl:stylesheet>
