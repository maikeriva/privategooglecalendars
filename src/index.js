import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { Fragment, useState } from '@wordpress/element';
import { CheckboxControl, PanelBody, TextControl, TextareaControl } from '@wordpress/components';

const defaultFullcalendarConfig = JSON.stringify({
    header: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,timeGridWeek,listWeek"
    }
});

function getNewUpdatedObject(obj, objName, key, newValue) {
    const copy = Object.assign({}, obj);
    copy[key] = newValue;
    const newObj = {};
    newObj[objName] = copy;
    return newObj;
}
 
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
                eventpopup: false,
                eventlink: false,
                eventdescription: false,
                eventlocation: false,
                eventattendees: false,
                eventattachments: false,
                eventcreator: false,
                eventcalendarname: false
            }
        },
        fullcalendarconfig: {
            type: "string",
            default: defaultFullcalendarConfig
        },
        hideoptions: {
            type: "object",
            default: {
                hidefuture: false,
                hidefuturedays: 0,
                hidepassed: false,
                hidepasseddays: 0,
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

        const [hasValidFullCalendarConfigValue, setHasValidFullCalendarConfigValue] = useState(hasValidFullCalendarConfigValueCheck(props.attributes.fullcalendarconfig));

        const calendars = props.attributes.calendars;
        const config = props.attributes.config;
        const hideoptions = props.attributes.hideoptions;
        const fullcalendarconfig = props.attributes.fullcalendarconfig;

        const onCalendarSelectionChange = function(newValue) {
            props.setAttributes(getNewUpdatedObject(calendars, "calendars", this, newValue));
        };

        const onCalendarConfigChange = function(newValue) {
            props.setAttributes(getNewUpdatedObject(config, "config", this, newValue));
        };

        const onHideoptionsChange = function(newValue) {
            props.setAttributes(getNewUpdatedObject(hideoptions, "hideoptions", this, newValue));
        };

        const onFullCalendarConfigChange = function(newValue) {
            setHasValidFullCalendarConfigValue(hasValidFullCalendarConfigValueCheck(newValue));
            props.setAttributes({fullcalendarconfig: newValue});
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

        const hidePassedDays = hideoptions.hidepassed
            ? 
            <TextControl label={`...more than ${hideoptions.hidepasseddays} days ago`} type="number" min={0}
                value={hideoptions.hidepasseddays} onChange={onHideoptionsChange.bind('hidepasseddays')} />
            : null;
        const hideFutureDays = hideoptions.hidefuture
            ?
            <TextControl label={`...more than ${hideoptions.hidefuturedays} days from now`} type="number" min={0}
                value={hideoptions.hidefuturedays} onChange={onHideoptionsChange.bind('hidefuturedays')} />
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
                        <CheckboxControl className="pgc-sidebar-row" onChange={onHideoptionsChange.bind('hidepassed')}
                            label="Hide passed events..." checked={hideoptions.hidepassed} />
                        {hidePassedDays}
                        <CheckboxControl className="pgc-sidebar-row" onChange={onHideoptionsChange.bind('hidefuture')}
                            label="Hide future events..." checked={hideoptions.hidefuture} />
                        {hideFutureDays}
                    </PanelBody>
                    <PanelBody
                        title="Popup options"
                        initialOpen={true}>
                        {eventPopupList}
                    </PanelBody>
                    <PanelBody
                        title="FullCalendar options"
                        initialOpen={false}>
                        <TextareaControl className={hasValidFullCalendarConfigValue ? "" : "has-error"} label="See https://fullcalendar.io/ for valid options" help={hasValidFullCalendarConfigValue ? "" : "Invalid JSON"} value={fullcalendarconfig} placeHolder={defaultFullcalendarConfig} onChange={onFullCalendarConfigChange} />
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
        const hideoptions = props.attributes.hideoptions;
        const fullcalendarconfig = props.attributes.fullcalendarconfig;
        let hasValidConfig = false;
        try {
            hasValidConfig = fullcalendarconfig && Object.keys(JSON.parse(fullcalendarconfig)).length > 0;
        } catch (ex) {
            console.log(ex);
        }
        if (hasValidConfig) {
            attrsArray.push(`fullcalendarconfig='${fullcalendarconfig}'`);
        }
        Object.keys(config).forEach(function(key) {
            attrsArray.push(key + '="' + (config[key] ? 'true' : 'false') + '"');
        });

        attrsArray.push(`hidepassed="${hideoptions.hidepassed ? hideoptions.hidepasseddays : 'false'}"`);
        attrsArray.push(`hidefuture="${hideoptions.hidefuture ? hideoptions.hidefuturedays : 'false'}"`);
        
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

        }

        return <p>[pgc {attrsArray.join(" ")}]</p>
    },
} );