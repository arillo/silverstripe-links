<% if $Link.Exists %>
  <% with $Link %>
    <a href="$Href" $TargetAttr.RAW class="$CssClass">$Title</a>
  <% end_with %>
<% end_if %>
