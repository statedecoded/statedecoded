<?xml version="1.0" encoding="us-ascii"?>

<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">
  <xsl:output media-type="text/xml" method="xml" indent="yes"/>

  <xsl:template match="/">
    <add>
      <xsl:apply-templates select="law"/>
    </add>
  </xsl:template>
 
  <xsl:template match="law">
    <doc>
        <field name="id"><xsl:value-of select="@id"/></field>
        <field name="catch_line"><xsl:value-of select="catch_line"/></field>
        <field name="text"><xsl:value-of select="text"/></field>
        <field name="section"><xsl:value-of select="section_number"/></field>
        <xsl:apply-templates select="structure"/>
        <xsl:apply-templates select="tags"/>
        <xsl:apply-templates select="refers_to"/>
        <xsl:apply-templates select="refered_by"/>
        <field name="type">law</field>
    </doc>
  </xsl:template>

  <xsl:template match="tags">
    <xsl:for-each select="tag">
        <field name="tag">
        <xsl:value-of select="current()"/> 
        </field>
     </xsl:for-each>
  </xsl:template>
  
  <xsl:template match="refers_to">
    <xsl:for-each select="reference">
        <field name="refers_to">
        <xsl:value-of select="current()"/> 
        </field>
     </xsl:for-each>
  </xsl:template>
  
  <xsl:template match="refered_by">
    <xsl:for-each select="reference">
        <field name="refered_by">
        <xsl:value-of select="current()"/> 
        </field>
     </xsl:for-each>
  </xsl:template>

  <xsl:template match="structure">
    <field name="structure">
        <xsl:for-each select="unit">
        <xsl:value-of select="current()"/> 
        <xsl:if test="not(position() = last())">/</xsl:if>

      </xsl:for-each>
    </field>
  </xsl:template>
 
</xsl:stylesheet>
