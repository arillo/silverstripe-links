<% if $Link.Exists %>
  <% with $Link %>
    <a href="$Href" $TargetAttr.RAW class="$Css">$Title</a>
  <% end_with %>
<% end_if %>
