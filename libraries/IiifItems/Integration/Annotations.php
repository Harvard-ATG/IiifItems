<?php

class IiifItems_Integration_Annotations extends IiifItems_BaseIntegration {
    public function install() {
        $elementTable = get_db()->getTable('Element');
        // Add Annotation item type elements
        $annotation_metadata = insert_item_type(array(
            'name' => 'Annotation',
            'description' => 'An OA-compliant annotation',
        ), array(
            array('name' => 'On Canvas', 'description' => 'URI of the attached canvas'),
            array('name' => 'Selector', 'description' => 'The SVG boundary area of the annotation'),
        ));
        set_option('iiifitems_annotation_item_type', $annotation_metadata->id);
        set_option('iiifitems_annotation_on_element', $elementTable->findByElementSetNameAndElementName('Item Type Metadata', 'On Canvas')->id);
        set_option('iiifitems_annotation_selector_element', $elementTable->findByElementSetNameAndElementName('Item Type Metadata', 'Selector')->id);
        $annotation_metadata_elements = array();
        foreach (get_db()->getTable('Element')->findByItemType($annotation_metadata->id) as $element) {
            $annotation_metadata_elements[] = $element->id;
        }
        set_option('iiifitems_annotation_elements', json_encode($annotation_metadata_elements));
        // Add Annotation item type Text element
        $addTextElementMigration = new IiifItems_Migration_0_0_1_5();
        $addTextElementMigration->up();
    }
    
    public function uninstall() {
        $elementSetTable = get_db()->getTable('ElementSet');
        $itemTypeTable = get_db()->getTable('ItemType');
        $annotationItemType = $itemTypeTable->find(get_option('iiifitems_annotation_item_type'));
        foreach (json_decode(get_option('iiifitems_annotation_elements')) as $_ => $element_id) {
            get_db()->getTable('Element')->find($element_id)->delete();
        }
        $annotationItemType->delete();
        delete_option('iiifitems_annotation_item_type');
        delete_option('iiifitems_annotation_on_element');
        delete_option('iiifitems_annotation_selector_element');
    }
    
    public function initialize() {
        add_filter(array('Display', 'Item', 'Item Type Metadata', 'On Canvas'), array($this, 'displayForAnnotationOnCanvas'));
        add_filter(array('ElementInput', 'Item', 'Item Type Metadata', 'On Canvas'), array($this, 'inputForAnnotationOnCanvas'));
        add_filter(array('ElementForm', 'Item', 'Item Type Metadata', 'On Canvas'), 'filter_singular_form');
        add_filter(array('ElementForm', 'Item', 'Item Type Metadata', 'Selector'), 'filter_singular_form');
        add_filter(array('ElementInput', 'Item', 'Item Type Metadata', 'Selector'), 'filter_minimal_input');
    }
    
    public function altHookAdminItemsBrowseSimpleEach($args) {
        $on = raw_iiif_metadata($args['item'], 'iiifitems_annotation_on_element');
        if (($attachedItem = find_item_by_uuid($on)) || ($attachedItem = find_item_by_atid($on))) {
            $text = 'Attached to: <a href="' . url(array('id' => $attachedItem->id, 'controller' => 'items', 'action' => 'show'), 'id') . '">' . metadata($attachedItem, array('Dublin Core', 'Title')) . '</a>';
            if ($attachedItem->collection_id !== null) {
                $collection = get_record_by_id('Collection', $attachedItem->collection_id);
                $collectionLink = url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id');
                $collectionTitle = metadata($collection, array('Dublin Core', 'Title'));                                                                                                      
                $text .= " (<a href=\"{$collectionLink}\">{$collectionTitle}</a>)";    
            }
            echo "<p>{$text}</p>";
        }
    }
    
    public function altHookAdminItemsShowSidebar($args) {
        if ($onCanvasUuid = raw_iiif_metadata($args['item'], 'iiifitems_annotation_on_element')) {
            $onCanvasMatches = get_db()->getTable('ElementText')->findBySql("element_texts.element_id = ? AND element_texts.text = ?", array(
                get_option('iiifitems_annotation_on_element'),
                $onCanvasUuid,
            ));
            $belongsTo = find_item_by_uuid($onCanvasUuid);
            echo '<div class="panel"><h4>Annotations</h4>'
                . '<p>This annotation is one of '
                . '<a href="' . admin_url('items') . '/browse?search=&advanced%5B0%5D%5Bjoiner%5D=and&advanced%5B0%5D%5Belement_id%5D=' . get_option('iiifitems_annotation_on_element') . '&advanced%5B0%5D%5Btype%5D=is+exactly&advanced%5B0%5D%5Bterms%5D=' . $onCanvasUuid . '">'
                . count($onCanvasMatches)
                . '</a>'
                . ' on the canvas "<a href="' . url(array('id' => $belongsTo->id, 'controller' => 'items', 'action' => 'show'), 'id') . '">'
                . metadata($belongsTo, array('Dublin Core', 'Title'))
                . '</a>".</p>'
                . '</div>';
            echo '<script>jQuery("#edit > a:first-child").after("<a href=\"" + ' . js_escape(admin_url(array('things' => 'items', 'id' => $belongsTo->id), 'iiifitems_annotate')) . ' + "\" class=\"big blue button\">Annotate</a>");</script>';
        }
    }
    
    public function displayForAnnotationOnCanvas($comps, $args) {
        $on = $args['element_text']->text;
        if (!($target = find_item_by_uuid($on))) {
            if (!($target = find_item_by_atid($on))) {
                return $on;
            }
        }
        $link = url(array('id' => $target->id, 'controller' => 'items', 'action' => 'show'), 'id');
        $title = metadata($target, array('Dublin Core', 'Title'));
        $text = "<a href=\"{$link}\">{$title}</a> ({$on})";
        if ($target->collection_id !== null) {
            $collection = get_record_by_id('Collection', $target->collection_id);
            $collectionLink = url(array('id' => $collection->id, 'controller' => 'collections', 'action' => 'show'), 'id');
            $collectionTitle = metadata($collection, array('Dublin Core', 'Title'));
            $text .= "<p>From collection <a href=\"{$collectionLink}\">{$collectionTitle}</a></p>";
        }
        return $text;
    }

    public function inputForAnnotationOnCanvas($comps, $args) {
        $comps['input'] = get_view()->formText($args['input_name_stem'] . '[text]', $args['value'], array('class' => 'five columns'));
        return filter_minimal_input($comps, $args);
    }
}
