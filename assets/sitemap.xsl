<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9"
  xmlns:xhtml="http://www.w3.org/1999/xhtml"
  xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
  xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
  exclude-result-prefixes="s xhtml image news">

  <xsl:output method="html" encoding="UTF-8" indent="yes"/>
  <xsl:strip-space elements="*"/>

  <!-- Dispatcher -->
  <xsl:template match="/">
    <xsl:apply-templates select="/s:urlset | /s:sitemapindex"/>
  </xsl:template>

  <!-- Common <head> block -->
  <xsl:template name="head">
    <head>
      <meta charset="utf-8"/>
      <meta name="viewport" content="width=device-width, initial-scale=1"/>
      <title>XML Sitemap</title>
      <style type="text/css"><![CDATA[
        html { background:#fff }
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; font-size:16px; line-height:1.5; color:#222; margin:24px }
        h1 { font-weight:600; margin:0 0 16px 0 }
        p  { margin:0 0 16px 0 }
        table { width:100%; border-collapse:collapse; margin-top:8px }
        th, td { border:1px solid #d0d0d0; padding:8px; vertical-align:top }
        th { background:#f7f7f7; text-align:left }
        a { color:#0645ad; text-decoration:none; border-bottom:1px dotted #0645ad }
        a:hover { text-decoration:underline; border-bottom-color:transparent }
        .muted { color:#777 }
        .nowrap { white-space:nowrap }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace }
        .count { font-variant-numeric: tabular-nums }
      ]]></style>
    </head>
  </xsl:template>

  <!-- URLSET view -->
  <xsl:template match="s:urlset">
    <html>
      <xsl:call-template name="head"/>
      <body>
        <h1>XML Sitemap</h1>
        <p class="muted">
          <span class="count"><xsl:value-of select="count(s:url)"/></span> URLs
        </p>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Location</th>
              <th>Alternate languages</th>
              <th class="nowrap">Last modified</th>
              <th>Images</th>
              <th>Changefreq</th>
              <th>Priority</th>
            </tr>
          </thead>
          <tbody>
            <!-- Primary sort: lastmod desc; Secondary: loc asc -->
            <xsl:for-each select="s:url">
              <xsl:sort select="normalize-space(s:lastmod)" order="descending"/>
              <xsl:sort select="translate(s:loc, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')"/>
              <tr>
                <td class="count"><xsl:number/></td>
                <td class="mono">
                  <a href="{s:loc}"><xsl:value-of select="s:loc"/></a>
                </td>
                <td>
                  <xsl:choose>
                    <xsl:when test="xhtml:link[@rel='alternate']">
                      <xsl:for-each select="xhtml:link[@rel='alternate']">
                        <a href="{@href}">
                          <xsl:value-of select="@hreflang"/>
                        </a>
                        <xsl:if test="position()!=last()">, </xsl:if>
                      </xsl:for-each>
                    </xsl:when>
                    <xsl:otherwise><span class="muted">none</span></xsl:otherwise>
                  </xsl:choose>
                </td>
                <td class="nowrap">
                  <xsl:choose>
                    <xsl:when test="string-length(normalize-space(s:lastmod))">
                      <xsl:value-of select="s:lastmod"/>
                    </xsl:when>
                    <xsl:otherwise><span class="muted">n/a</span></xsl:otherwise>
                  </xsl:choose>
                </td>
                <td>
                  <xsl:variable name="imgcount" select="count(image:image)"/>
                  <xsl:choose>
                    <xsl:when test="$imgcount &gt; 0">
                      <a href="{image:image[1]/image:loc}">
                        <xsl:value-of select="image:image[1]/image:loc"/>
                      </a>
                      <xsl:if test="$imgcount &gt; 1">
                        <xsl:text> (+</xsl:text><xsl:value-of select="$imgcount - 1"/><xsl:text> more)</xsl:text>
                      </xsl:if>
                    </xsl:when>
                    <xsl:otherwise><span class="muted">none</span></xsl:otherwise>
                  </xsl:choose>
                </td>
                <td>
                  <xsl:choose>
                    <xsl:when test="string-length(normalize-space(s:changefreq))">
                      <xsl:value-of select="s:changefreq"/>
                    </xsl:when>
                    <xsl:otherwise><span class="muted">n/a</span></xsl:otherwise>
                  </xsl:choose>
                </td>
                <td>
                  <xsl:choose>
                    <xsl:when test="string-length(normalize-space(s:priority))">
                      <xsl:value-of select="format-number(number(s:priority), '0.0')"/>
                    </xsl:when>
                    <xsl:otherwise><span class="muted">n/a</span></xsl:otherwise>
                  </xsl:choose>
                </td>
              </tr>
            </xsl:for-each>
          </tbody>
        </table>
      </body>
    </html>
  </xsl:template>

  <!-- SITEMAP INDEX view -->
  <xsl:template match="s:sitemapindex">
    <html>
      <xsl:call-template name="head"/>
      <body>
        <h1>XML Sitemap Index</h1>
        <p class="muted">
          <span class="count"><xsl:value-of select="count(s:sitemap)"/></span> sitemap files
        </p>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Location</th>
              <th class="nowrap">Last modified</th>
            </tr>
          </thead>
          <tbody>
            <xsl:for-each select="s:sitemap">
              <xsl:sort select="normalize-space(s:lastmod)" order="descending"/>
              <xsl:sort select="translate(s:loc, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')"/>
              <tr>
                <td class="count"><xsl:number/></td>
                <td class="mono"><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></td>
                <td class="nowrap">
                  <xsl:choose>
                    <xsl:when test="string-length(normalize-space(s:lastmod))">
                      <xsl:value-of select="s:lastmod"/>
                    </xsl:when>
                    <xsl:otherwise><span class="muted">n/a</span></xsl:otherwise>
                  </xsl:choose>
                </td>
              </tr>
            </xsl:for-each>
          </tbody>
        </table>
      </body>
    </html>
  </xsl:template>

</xsl:stylesheet>
