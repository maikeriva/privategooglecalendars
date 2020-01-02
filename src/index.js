import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { Fragment, useState, useEffect } from '@wordpress/element';
import { CheckboxControl, PanelBody, TextControl, TextareaControl, Modal } from '@wordpress/components';

const defaultFullcalendarConfig = JSON.stringify({
    header: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,timeGridWeek,listWeek"
    }
}, null, 2);

function getNewUpdatedObject(obj, objName, key, newValue) {
    const copy = Object.assign({}, obj);
    copy[key] = newValue;
    const newObj = {};
    newObj[objName] = copy;
    return newObj;
}

function hasValidFullCalendarConfigValueCheck(value) {
    try {
        return value === "" || Object.keys(JSON.parse(value)).length > 0;
    } catch (ex) {
        return false;
    }
}

const MyInfoModal = function(props) {
    return (
        <Modal
            title="FullCalendar config"
            onRequestClose={ props.onClose }>
            <p>Copy the default FullCalendar config if you want to change it. This is the configuration object that you can set as the second argument in the <code>FullCalendar.Calendar</code> constructor.</p>
            <p>See the <a target="_blank" href="https://fullcalendar.io/docs#toc">FullCalendar documentation</a> for available configuration options.</p>

        </Modal>
    );
};
 
registerBlockType('pgc-plugin/calendar', {
    title: 'Private Google Calendars',
    icon: 'calendar',
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
            default: ""
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

        const [hasValidFullCalendarConfigValue, setHasValidFullCalendarConfigValue]
            = useState(hasValidFullCalendarConfigValueCheck(props.attributes.fullcalendarconfig));
        const [showConfigArea, setShowConfigArea] = useState(props.attributes.fullcalendarconfig);
        const [showInfoModal, setShowInfoModal] = useState(false);

        const calendars = props.attributes.calendars;
        let selectedCalendarCount = 0;
        Object.keys(calendars).forEach(function(key) {
            if (calendars[key]) selectedCalendarCount += 1;
        });
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
            props.setAttributes({fullcalendarconfig: newValue === "" ? "" : newValue});
        };

        const onAreaKeyDown = function(e) {
            if (e.keyCode == 9) {
                e.preventDefault();
                const area = e.target;
                const start = area.selectionStart;
                const content = area.value.substring(0, start) + "  " + area.value.substring(area.selectionEnd);
                onFullCalendarConfigChange(content);
                let t = setTimeout(() => {
                    clearTimeout(t);
                    area.selectionEnd = start + 2;
                }, 0);
            }
        };

        const calendarList = Object.keys(window.pgc_selected_calendars).map((id) => {
            const calendar = window.pgc_selected_calendars[id];
            return <CheckboxControl style={{backgroundColor: calendar.backgroundColor}} className="pgc-sidebar-row" onChange={onCalendarSelectionChange.bind(id)}
                label={calendar.summary} checked={(id in calendars) && calendars[id]} />
        });

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

        useEffect(() => {
            const unsubscribe = wp.data.subscribe(function () {
                if (wp.data.select("core/editor")) {
                    const isSavingPost = wp.data.select('core/editor').isSavingPost();
                    const isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();
                    if (isSavingPost && !isAutosavingPost) {
                        if (!hasValidFullCalendarConfigValue) {
                            // Infinite loop when directly called, don't know why.
                            let t = setTimeout(function() {
                                clearTimeout(t);
                                wp.data.dispatch("core/notices").createWarningNotice("Malformed JSON, this calendar will probably not display correctly");
                            }, 0);
                            unsubscribe();
                        }
                    }
                }
            });
            return unsubscribe;
        });

        const fullCalendarConfigArea = showConfigArea ? (
            <Fragment>
                <TextareaControl rows={10} onKeyDown={onAreaKeyDown}
                    className={"pgc-fullcalendarconfigarea " + (hasValidFullCalendarConfigValue ? "" : "has-error")}
                    value={fullcalendarconfig}
                    help={!hasValidFullCalendarConfigValue ? "Malformed JSON" : ""}
                    placeHolder={defaultFullcalendarConfig} onChange={onFullCalendarConfigChange} />
                <div className="pgc-copy-link">
                    <a href="#" onClick={(e) => {e.preventDefault(); onFullCalendarConfigChange(defaultFullcalendarConfig)}}>Copy default FullCalendar config</a>
                    <span onClick={() => setShowInfoModal(true)} class="dashicons dashicons-editor-help"></span>
                </div>
            </Fragment>
        ) : null;

        const infoModal = showInfoModal ? MyInfoModal({onClose: () => {setShowInfoModal(false)}}) : null;

        return (
            <Fragment>
                <InspectorControls>    
                    <PanelBody
                        title={"Selected Google calendars (" + (selectedCalendarCount === 0 ? "All" : selectedCalendarCount) + ")"}
                        initialOpen={true}>
                        {calendarList}
                    </PanelBody>
                    <PanelBody
                        title="Calendar options"
                        initialOpen={true}>
                        <CheckboxControl className="pgc-sidebar-row" onChange={onCalendarConfigChange.bind('filter')}
                            label="Show calendar filter" checked={config.filter} />
                        <CheckboxControl className="pgc-sidebar-row" onChange={setShowConfigArea}
                            label="Edit FullCalendar config" checked={showConfigArea} />
                        <CheckboxControl className="pgc-sidebar-row" onChange={onHideoptionsChange.bind('hidepassed')}
                            label="Hide passed events..." checked={hideoptions.hidepassed} />
                        {hidePassedDays}
                        <CheckboxControl className="pgc-sidebar-row" onChange={onHideoptionsChange.bind('hidefuture')}
                            label="Hide future events..." checked={hideoptions.hidefuture} />
                        {hideFutureDays}
                    </PanelBody>
                    <PanelBody
                        title={"Popup options (" + (config.eventpopup ? "Show" : "Hide") + ")"}
                        initialOpen={true}>
                        {eventPopupList}
                    </PanelBody>
                </InspectorControls>
                <div>Private Google Calendars Block</div>
                {fullCalendarConfigArea}
                {infoModal}
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
            //console.log(ex);
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