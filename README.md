# Principal

This PHP script crawls through a text document and converts it to HTML based on rules. This script has existed on sites of mine in some form since ever, I wrote the first version in 2004.

The document is divided into blocks separated by two new lines. Use of more than two lines between blocks will work, but may cause unexpected behavior. Also be mindful of a gap between blocks that has a space or tab in the middle line.

Principal generates the following HTML tags:
\<p>, \<pre>, \<blockquote>, \<ul>, \<ol>, \<li>, \<dl>, \<dt>, \<dd>, \<h1...>, \<hr/>, \<br/>
From simple text as defined in the following examples...

    - blocks where ALL of the lines
    - begin with a hyphen and a space
    - will automatically be converted 
    - to a <ul> unordered bullet list.

    1. blocks where lines begin with 1.
    2. 3. etc... or just 0. will create
    0. a <ol> ordered list. Zero is 
    0. used so you can re-arrange the
    0. items without having to re-number.
    17. The number is actually ignored.

    - blocks with a mix of hyphens and
    No hyphens at the beginning of the line
    - Will create a <dl> definition list
    - with <dt> titles
    and <dd> description blocks.

    >> blocks beginning with > create headers
    
    >>> header 3
    
    >>>> header 4, etc...
    
    <div class="left-frame"><p>
    While blocks that begin with &lt; are 
    added as ordinary HTML with no special 
    rules applied.</p></div>

    "Open a block with a " character to
    create a <blockquote>. The trailing
    quote is not necessary and removed.

     Blocks  that   open    with   a
    Space    create    preformatted 
    text    blocks   where   spaces 
    are     literal.     Bear    in
    mind   that  the   first  space
    is   removed.
    
    Blocks with no other discernable rule
    create an ordinary paragraph. Paragraphs
    can be on one line that wraps, or line 
    breaks inside the paragraph will create
    <br/> tags to break the line.
    
## Inline elements

Principal generates the following HTML tags:
\<a>, \<b>, \<i>, \<s>, \<u>, \<q>, \<em>, \<tt>, \<strong>, \<img>
While rendering text into any of these block tags:
\<p>, \<blockquote>, \<ul>, \<ol>, \<li>, \<dl>, \<dt>, \<dd>, \<h1...>
But not for any other tags or plain HTML...

### Creating links in your documents

You're going to want to link to other pages on your site and to external resources. If you're at all familiar with HTML, this involves the usage of: \<a href="url">text\</a>, but this shortcode vastly simplifies it.

    http://website.com/page.htm (text)
    
    www.another-website.com (text)
    
    ./local-page.htm (text)
    
    .//top-level-page.htm (text)

If the next non-space character after the link is not a ( then the URL will just be the text inside the link. Otherwise the text in the brackets becomes the link text. External pages recognize **http://** or **www.** and automatically open in a new tab so users don't leave your website when they click on them. Note that local page URLs start with **./** using only **/** will not work! That will actually make *italic text.*

Use this syntax to make links in paragraphs, lists and blockquotes. Doesn't apply inside \<pre> elements or plain HTML blocks. Just put the URL followed by the text in (brackets). Easy as that!

### Putting images in documents

Principal provides an easy shortcut to insert images with **::** followed by the path of the image, whether it's a local image or a remote image with **::http://...** you may use this **::** shortcut inside any text block, or open a block with **::** to create an image outside any other tag.

### Use shortcodes for font styling

Principal lets you apply *font styling* using the special characters:

    *  bold      /  italic      ~  strike        _  underline  
    
    "  quote     ^  emphasis    |  typewriter    =  strong

The special characters must be at the *start of the block* or immediately after whitespace, hyphen or a new line. The characters must be followed by an upper or lower case letter or a hyphen to open a tag, but the tag will end at the next matching character unconditionally. Also note that these tags do not nest, and images or links cannot be put inside these formatting tags as of this version.


