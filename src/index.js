import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { Fragment, useState } from '@wordpress/element';
import { CheckboxControl, PanelBody, TextControl, TextareaControl } from '@wordpress/components';
 
registerBlockType('pgc-plugin/calendar', {
    title: 'Private Google Calendars Block',
    icon: 'universal-access-alt',
    category: 'widgets',
    attributes: {
        calendars: {
            type: "object",
            default: {}
        },
        config: {
            type: "object",
            default: {
                filter: true,
                hidefuture: false,
                hidefuturedays: 0,
                hidepassed: false,
                hidepasseddays: 0,
                eventpopup: false,
                eventlink: false,
                eventdescription: false,
                eventlocation: false,
                eventattendees: false,
                eventattachments: false,
                eventcreator: false,
                eventcalendarname: false,
                fullcalendarconfig: null
            }
        }
    },
    edit(props) {

        const hasValidFullCalendarConfigValueCheck = function(value) {
            try {
                return value === null || value === "" || Object.keys(JSON.parse(value)).length > 0;
            } catch (ex) {
                return false;
            }
        };

        const [hasValidFullCalendarConfigValue, setHasValidFullCalendarConfigValue] = useState(hasValidFullCalendarConfigValueCheck(props.attributes.config.fullcalendarconfig));

        const calendars = props.attributes.calendars;
        const config = props.attributes.config;

        const onCalendarSelectionChange = function(newValue) {
            var copy = Object.assign({}, calendars);
            copy[this] = newValue;
            props.setAttributes({calendars: copy});
        };

        const onCalendarConfigChange = function(newValue) {
            var copy = Object.assign({}, config);
            copy[this] = newValue;
            props.setAttributes({config: copy});
        };

        const onDaysChange = function(newValue) {
            var copy = Object.assign({}, config);
            copy[this] = newValue;
            props.setAttributes({config: copy});
        };

        const onFullCalendarConfigChange = function(newValue) {
            setHasValidFullCalendarConfigValue(hasValidFullCalendarConfigValueCheck(newValue));
            var copy = Object.assign({}, config);
            copy.fullcalendarconfig = newValue;
            props.setAttributes({config: copy});
        };

        //console.log(window.pgc_selected_calendars);
        // TODO: calendar color
        const calendarList = Object.keys(window.pgc_selected_calendars).map((id) => {
            const calendar = window.pgc_selected_calendars[id];
            return <CheckboxControl className="pgc-sidebar-row" onChange={onCalendarSelectionChange.bind(id)}
                label={calendar.summary} checked={(id in calendars) && calendars[id]} />
        });

        // TODO: locale?
        const eventPopupList = [
            ["eventpopup", "Show event popup"],
            ["eventlink", "Show event link"],
            ["eventdescription", "Show event description"],
            ["eventlocation", "Show event location"],
            ["eventattendees", "Show event attendees"],
            ["eventattachments", "Show event attachments"],
            ["eventcreator", "Show event creator"],
            ["eventcalendarname", "Show calendarname"],
        ].map((item) => {
            return <CheckboxControl className="pgc-sidebar-row" onChange={onCalendarConfigChange.bind(item[0])}
                label={item[1]} checked={config[item[0]]} />;
        });

        const hidePassedDays = config.hidepassed
            ? 
            <TextControl label={`...more than ${config.hidepasseddays} days ago`} type="number" min={0}
                value={config.hidepasseddays} onChange={onDaysChange.bind('hidepasseddays')} />
            : null;
        const hideFutureDays = config.hidefuture
            ?
            <TextControl label={`...more than ${config.hidefuturedays} days from now`} type="number" min={0}
                value={config.hidefuturedays} onChange={onDaysChange.bind('hidefuturedays')} />
            : null;

        return (
            <Fragment>
                <InspectorControls>    
                    <PanelBody
                        title="Calendars"
                        initialOpen={true}>
                        {calendarList}
                    </PanelBody>
                    <PanelBody
                        title="Calendar options"
                        initialOpen={true}>
                        <CheckboxControl className="pgc-sidebar-row" onChange={onCalendarConfigChange.bind('filter')}
                            label="Show calendar filter" checked={config.filter} />
                        <CheckboxControl className="pgc-sidebar-row" onChange={onCalendarConfigChange.bind('hidepassed')}
                            label="Hide passed events..." checked={config.hidepassed} />
                        {hidePassedDays}
                        <CheckboxControl className="pgc-sidebar-row" onChange={onCalendarConfigChange.bind('hidefuture')}
                            label="Hide future events..." checked={config.hidefuture} />
                        {hideFutureDays}
                        <TextareaControl className={hasValidFullCalendarConfigValue ? "" : "has-error"} label="FullCalendar config" help={hasValidFullCalendarConfigValue ? "JSON" : "Enter valid JSON"} value={config.fullcalendarconfig} onChange={onFullCalendarConfigChange} />
                    </PanelBody>
                    <PanelBody
                        title="Popup options"
                        initialOpen={true}>
                        {eventPopupList}
                    </PanelBody>
                </InspectorControls>
                <div>Private Google Calendars Block</div>
            </Fragment>
        );
    },
    save(props) {
        const attrs = {};
        const attrsArray = [];
        const config = props.attributes.config;
        if (Object.keys(props.attributes.calendars).length) {
            const calendarids = [];
            Object.keys(props.attributes.calendars).forEach(function(id) {
                if ((id in props.attributes.calendars) && props.attributes.calendars[id]) {
                    calendarids.push(id);
                }
            });
            if (calendarids.length) {
                attrs.calendarids = calendarids.join(",");
            }
            Object.keys(attrs).forEach(function(key) {
                attrsArray.push(key + '="' + attrs[key] + '"');
            });

            Object.keys(config).forEach(function(key) {
                if (key.substr(0, 4) === "hide") {
                    return;
                }
                attrsArray.push(key + '="' + (config[key] ? 'true' : 'false') + '"');
            });

            // hide logic
            //if (config.hidepassed) {
            attrsArray.push(`hidepassed="${config.hidepassed ? config.hidepasseddays : 'false'}"`);
            attrsArray.push(`hidefuture="${config.hidefuture ? config.hidefuturedays : 'false'}"`);
            //}
        }
        return <p>[pgc {attrsArray.join(" ")}]</p>
    },
} );