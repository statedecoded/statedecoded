<?xml version="1.0" encoding="UTF-8" ?>
			
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:orig="http://StatRev.xsd"
	xmlns:fn="http://localhost/"  xmlns:xs="http://www.w3.org/2001/XMLSchema" >

	<!-- Strip whitespace from everything except the text of laws. -->
	<xsl:strip-space elements="*" />
	<xsl:preserve-space elements="bodyText" />

	<!-- Don't include any whitespace-only text nodes. -->
	<xsl:strip-space elements="*" />
	
	<xsl:output
			method="xml"
			version="1.0"
			encoding="utf-8"
			omit-xml-declaration="no"
			indent="yes"
			media-type="text/xml"/>

	<!--Start processing at the top-level element.-->
	<xsl:template match="legislativeDoc">
		<law>

			<structure>
				<xsl:for-each select="metadata/hierarchy">
					<xsl:apply-templates select="hierarchyLevel"/>
				</xsl:for-each>
			</structure>
			
			<!--Strip out the leading "§ " and the trailing period.-->
			<xsl:variable name="section-number" select="translate(legislativeDocBody/statute/level/heading/desig, '§ ', '')"/>
			<xsl:variable name="section-number-length" select="string-length($section-number)"/>
			<section_number><xsl:value-of select="substring($section-number, 1, ($section-number-length - 1))" /></section_number>

			<!--Include the catch line.-->
			<catch_line><xsl:value-of select="legislativeDocBody/statute/level/heading/title" /></catch_line>
			
			<history><xsl:value-of select="normalize-space(legislativeDocBody/statute/level/history/historyGroup/historyItem/bodyText)" /></history>
			
			<text>
				<xsl:for-each select="legislativeDocBody/statute">
					<xsl:apply-templates select="level"/>
				</xsl:for-each>
			</text>

		</law>
		
	</xsl:template>

	<!-- Recurse through structural hierarchies. -->	
	<xsl:template match="hierarchyLevel">
		<unit>
		
			<xsl:attribute name="label">
				<xsl:value-of select="@levelType"/>
			</xsl:attribute>

			<xsl:attribute name="identifier">
				<xsl:value-of select="replace(replace(normalize-space(heading/desig), '^(TITLE|SUBTITLE|ARTICLE|CHAPTER|PART) ', '' ), '.$', '')"/>
			</xsl:attribute>

			<!-- Counter -->
			<xsl:attribute name="level">
			  <xsl:value-of select="count(ancestor::hierarchyLevel) + 1"/>
			</xsl:attribute>

			<xsl:value-of select="fn:capitalize_phrase(heading/title)"/>
		
		</unit>

		<xsl:if test="hierarchyLevel">
  			<xsl:apply-templates select="hierarchyLevel"/>
		</xsl:if>
		
	</xsl:template>

	<!--Recurse through textual hierarchies (e.g., § 1(a)(iv)).-->
	<xsl:template match="level">

		<!-- Counter -->
		<xsl:variable name="depth" select="count(ancestor::level)"/>

		<!-- Handle  -->
		<xsl:choose>

			<!-- Only include a prefix if we're at least 1 level deep. -->
			<xsl:when test="$depth > 0">
				<section>
					<xsl:attribute name="prefix">
						<xsl:variable name="prefix_length" select="string-length(heading/desig)"/>
						<xsl:value-of select="substring(heading/desig, 0, $prefix_length)"/>
					</xsl:attribute>
					
					<xsl:value-of select="bodyText" />

					<xsl:if test="level">
						<xsl:apply-templates select="level"/>
					</xsl:if>

				</section>
			</xsl:when>

			<xsl:otherwise>
				<xsl:value-of select="bodyText" />
				<xsl:if test="level">
					<xsl:apply-templates select="level"/>
				</xsl:if>
			</xsl:otherwise>

		</xsl:choose>

	</xsl:template>

	<xsl:function name="fn:capitalize_word">
		<xsl:param name="word" as="xs:string" />
		<xsl:value-of select="concat( upper-case(substring( $word, 1, 1 )), lower-case(substring($word,2)) )" />
	</xsl:function>

	<xsl:function name="fn:capitalize_phrase">
		<xsl:param name="phrase" as="xs:string" />
		<xsl:variable name="tokens">
		<xsl:for-each select="tokenize( normalize-space($phrase), ' ' )">
			<xsl:value-of select="concat(fn:capitalize_word(.), ' ')"/>
		</xsl:for-each>
		</xsl:variable>
		<xsl:value-of select="substring(string($tokens),1,string-length(string($tokens))-1)"/>
	</xsl:function>

</xsl:stylesheet>
